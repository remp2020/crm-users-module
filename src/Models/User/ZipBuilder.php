<?php

namespace Crm\UsersModule\Models\User;

use ZipArchive;

class ZipBuilder
{
    private $tempDir;

    public function __construct($tempDir)
    {
        $this->tempDir = $tempDir;
    }

    public function getZipFile(): ZipArchive
    {
        $file = tempnam($this->tempDir, "zip");
        $zip = new ZipArchive();
        $zip->open($file, ZipArchive::OVERWRITE);
        return $zip;
    }
}
