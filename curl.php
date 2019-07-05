<?php

function _isCurl() {
    return function_exists('curl_version');
}

function dino_file_get_contents($site_url) {
    if (_isCurl()) {
        try {
            $ch = curl_init();
            $timeout = 10;
            curl_setopt($ch, CURLOPT_URL, $site_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Accept: application/json'));
            curl_setopt($ch, CURLOPT_USERAGENT, 'DinoNews');
            $file_contents = curl_exec($ch);

            if ($file_contents === false) {
                echo "cURL Error: " . curl_error($ch);
            }

            curl_close($ch);
            return $file_contents;
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    } else {
        try {
            return file_get_contents($site_url);
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
    return null;
}