<?php

namespace App\Helpers;

class FileHelper
{
  public static function getFileIcon($file)
  {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    return match ($ext) {
      'pdf' => 'fa-file-pdf',
      'doc', 'docx' => 'fa-file-word',
      'xls', 'xlsx' => 'fa-file-excel',
      'ppt', 'pptx' => 'fa-file-powerpoint',
      'jpg', 'jpeg', 'png', 'gif', 'svg' => 'fa-file-image',
      'zip', 'rar', '7z' => 'fa-file-zipper',
      'txt' => 'fa-file-lines',
      default => 'fa-file',
    };
  }
}
