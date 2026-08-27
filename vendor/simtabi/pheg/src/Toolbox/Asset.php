<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox;

final class Asset
{

    public function __construct() {}


    /**
     * A Simple PHP script to generate a unique hash using MD5 and some clever naming of CSS files or just about any file based on the actual file's content.
     * Why?
     * Using a hash based name system we can be certain when we need to break cache for a user
     * Instead of just forcing a cache break every x hours or days.
     * This ensures if the content ever changes the user definitely gets the latest version!
     * With this method we can set basically unlimited cache lengths.
     * @param  string $filename     File path/name input to be output with cache busting
     * @param string $rewrite      [Default 'hard']: Whether to create a new copy of file with unique name (hard) or append a get/query string to end of file (soft)
     * @param string $childFolder [Default 'dist']: Which sub-folder to place the output file into. Passing a blank string '' puts the file at the same location as the input
     * @return string               Hashed file name including folders in path where relevant
     *@author    Alex Wigmore <@mrwigster>
     * @link      https://alex-wigmore.co.uk
     * @copyright Alex Wigmore 2017-2018+
     * ----------------------------
     * You shouldn't need to edit this file.
     *
     * @since v1
     */
    public function hashFileName(string $fileName, string $rewrite = 'hard', string $childFolder = 'dist'): bool|string
    {
        $pathParts    = pathinfo($fileName);
        $fileNameName = $pathParts['filename']; // filename is only since PHP 5.2.0
        $name         = $fileNameName;
        $extension    = $pathParts['extension'];

        // md5 the contents and convert the outputted hash to a smaller string as we don't need the long precision of a true md5 hash.
        // STRING $name - adds a context to the output CSS file, instead of being a completely random string, we still have reference to it.
        $hash   = substr(base_convert(md5_file($fileName), 8, 32), 0, 12);
        $hashed = $name.'-'.$hash;


        if ($rewrite == 'hard') {
            if ($childFolder == '') {
                $distFolder = $pathParts['dirname'].'/';
            } else {
                $distFolder = $pathParts['dirname'].'/'.$childFolder .'/';
            }
            // Check if the output directory already exists
            if(!is_dir($distFolder)){
                // If directory doesn't exist, lets create it.
                mkdir($distFolder, 0755, true);
            }
            // Form a string of location to put the fileName with the hashed name for cache busting.
            $hashedfileName = $distFolder.$hashed.'.'.$extension;

            // Check if the file we've just generated a hash for has been created previously
            // If it HASN'T then delete old versions to keep the folder space clean and create it.
            if ( !file_exists($hashedfileName) ) {
                // Delete any other previously generated cache buster files, that begin with our file name
                // (be careful if you have multiple files beginning with same name)
                $deleteFiles = $distFolder.$name.'-*.*';
                array_map('unlink', glob($deleteFiles));

                // Copy the fileName with new cache busting hashed name to the distribution folder
                if (copy($fileName, $hashedfileName)) {
                    // This is what we want:
                    // Output the hashed file name.
                    return $hashedfileName;
                }
            } else {
                // File already existed and process done before.
                // Outputting:
                return $hashedfileName;
            }
        } elseif ($rewrite == 'soft') {
            return $fileName.'?v='.$hash;
        }

        return false;
    }
}