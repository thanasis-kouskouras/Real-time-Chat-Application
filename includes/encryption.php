<?php
/* ENCRYPTION FUNCTIONS

Provides AES-256-GCM symmetric encryption for chat message text.
Used when saving messages to the database and when reading them back.
Each message is encrypted with a unique random salt and IV, so two identical messages will never produce the same ciphertext. */

require_once __DIR__ . '/../config.php';

//Encrypt a plaintext string using AES-256-GCM
function encrypt($plaintext, $encoding = 'hex'): bool|string
{
    $password = ENCRYPTION_KEY;
    if (empty($password)) {
        return false;
    }
    if ($plaintext != null) {
        //Generate a unique random salt for each encryption (ensures identical plaintexts produce different ciphertexts)
        $keysalt = openssl_random_pseudo_bytes(16);

        //Derive a 256-bit key from the password and salt using PBKDF2-SHA512
        $key = hash_pbkdf2("sha512", $password, $keysalt, 20000, 32, true);

        //Generate a random IV (12 bytes for GCM mode)
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length("aes-256-gcm"));

        $tag = "";
        //Encrypt with AES-256-GCM. $tag receives the 16-byte authentication tag.
        $encryptedstring = openssl_encrypt($plaintext, "aes-256-gcm", $key, OPENSSL_RAW_DATA, $iv, $tag, "", 16);

        //Pack salt + IV + ciphertext + auth tag into a single binary blob, then encode
        $blob = $keysalt . $iv . $encryptedstring . $tag;
        return $encoding == "hex"
            ? bin2hex($blob)
            : ($encoding == "base64" ? base64_encode($blob) : $blob);
    }
    return false;
}

// Decrypt a string that was encrypted by encrypt().
function decrypt($encryptedstring, $encoding = 'hex'): bool|string
{
    $password = ENCRYPTION_KEY;
    if (empty($password)) {
        return false;
    }
    if ($encryptedstring != null) {
        try {
            /* Decode from hex/base64 back to raw binary.
            Suppress warnings for malformed input. */
            $decoded = $encoding == "hex"
                ? @hex2bin($encryptedstring)
                : ($encoding == "base64" ? @base64_decode($encryptedstring) : $encryptedstring);

            //If decoding failed the value was never encrypted, return original string
            if ($decoded === false) {
                return $encryptedstring;
            }

            //Extract the 16-byte salt from the start of the blob and re-derive the key
            $keysalt = substr($decoded, 0, 16);
            $key = hash_pbkdf2("sha512", $password, $keysalt, 20000, 32, true);

            //Extract the IV that follows the salt
            $ivlength = openssl_cipher_iv_length("aes-256-gcm");
            $iv = substr($decoded, 16, $ivlength);

            //Extract the 16-byte authentication tag from the end of the blob
            $tag = substr($decoded, -16);

            //Decrypt, the ciphertext sits between the IV and the auth tag
            $decrypted = @openssl_decrypt(substr($decoded, 16 + $ivlength, -16), "aes-256-gcm", $key, OPENSSL_RAW_DATA, $iv, $tag);

            //If decryption failed (wrong key, corrupted data), return original string
            if ($decrypted === false) {
                return $encryptedstring;
            }

            return $decrypted;
        } catch (Exception $e) {
            //If any unexpected error occurs, return the original string
            return $encryptedstring;
        }
    }
    return false;
}