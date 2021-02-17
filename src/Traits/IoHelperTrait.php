<?php

namespace SleekDB\Traits;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SleekDB\Exceptions\IOException;
use SleekDB\Exceptions\JsonException;

trait IoHelperTrait {

  /**
   * @param string $path
   * @throws IOException
   */
  private static function _checkWrite(string $path)
  {
    if(file_exists($path) === false){
      $path = dirname($path);
    }
    // Check if PHP has write permission
    if (!is_writable($path)) {
      throw new IOException(
        "Directory or file is not writable at \"$path\". Please change permission."
      );
    }
  }

  /**
   * @param string $path
   * @throws IOException
   */
  private static function _checkRead(string $path)
  {
    // Check if PHP has read permission
    if (!is_readable($path)) {
      throw new IOException(
        "Directory or file is not readable at \"$path\". Please change permission."
      );
    }
  }

  /**
   * @param $filePath
   * @return string
   * @throws IOException
   */
  private static function getFileContent(string $filePath){

    self::_checkRead($filePath);

    if(!file_exists($filePath)) {
      throw new IOException("File does not exist: $filePath");
    }

    $content = false;
    $fp = fopen($filePath, 'rb');
    if(flock($fp, LOCK_SH)){
      $content = stream_get_contents($fp);
    }
    flock($fp, LOCK_UN);
    fclose($fp);

    if($content === false) {
      throw new IOException("Could not retrieve the content of a file. Please check permissions at: $filePath");
    }

    return $content;
  }

  private static function writeContentToFile(string $filePath, string $content){

    self::_checkWrite($filePath);

    // Wait until it's unlocked, then write.
    if(file_put_contents($filePath, $content, LOCK_EX) === false){
      throw new IOException("Could not write content to file. Please check permissions at: $filePath");
    }
  }

  /**
   * @param string $folderPath
   * @return bool
   * @throws IOException
   */
  private static function deleteFolder(string $folderPath){
    self::_checkWrite($folderPath);
    $it = new RecursiveDirectoryIterator($folderPath, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
      self::_checkWrite($file);
      if ($file->isDir()) {
        rmdir($file->getRealPath());
      } else {
        unlink($file->getRealPath());
      }
    }
    return rmdir($folderPath);
  }

  /**
   * @param string $folderPath
   * @throws IOException
   */
  private static function createFolder(string $folderPath){
    self::_checkWrite($folderPath);
    // Check if the data_directory exists or create one.
    if (!file_exists($folderPath) && !mkdir($folderPath, 0777, true) && !is_dir($folderPath)) {
      throw new IOException(
        'Unable to create the a directory at ' . $folderPath
      );
    }
  }

  /**
   * @param string $filePath
   * @param \Closure $updateContentFunction
   * @return mixed
   * @throws IOException
   */
  private static function updateFileContent(string $filePath, \Closure $updateContentFunction){
    self::_checkRead($filePath);
    self::_checkWrite($filePath);

    $content = false;

    $fp = fopen($filePath, 'rb');
    if(flock($fp, LOCK_SH)){
      $content = stream_get_contents($fp);
    }
    flock($fp, LOCK_UN);
    fclose($fp);

    if($content === false){
      throw new IOException("Could not get shared lock for file: $filePath");
    }

    $content = $updateContentFunction($content);

    if(!is_string($content)){
      $encodedContent = json_encode($content);
      if($encodedContent === false){
        $content = (!is_object($content) && !is_array($content) && !is_null($content)) ? $content : gettype($content);
        throw new JsonException("Could not encode content with json_encode. Content: \"$content\".");
      }
      $content = $encodedContent;
    }


    if(file_put_contents($filePath, $content, LOCK_EX) === false){
      throw new IOException("Could not write content to file. Please check permissions at: $filePath");
    }


    return $content;
  }

  /**
   * @param string $filePath
   * @return bool
   */
  private static function deleteFile(string $filePath){

    if(false === file_exists($filePath)){
      return true;
    }
    try{
      self::_checkWrite($filePath);
    }catch(\Exception $exception){
      return false;
    }

    return @unlink($filePath) && !file_exists($filePath);
  }

  /**
   * @param array $filePaths
   * @return bool
   */
  private static function deleteFiles(array $filePaths){
    foreach ($filePaths as $filePath){
      // if a file does not exist, we do not need to delete it.
      if(true === file_exists($filePath)){
        try{
          self::_checkWrite($filePath);
          if(false === @unlink($filePath) || file_exists($filePath)){
            return false;
          }
        } catch (\Exception $exception){
          return false;
        }
      }
    }
    return true;
  }
}