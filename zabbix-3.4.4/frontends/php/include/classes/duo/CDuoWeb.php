<?php

/*
** Copyright (c) 2011, Duo Security, Inc.
** All rights reserved.
** 
** Redistribution and use in source and binary forms, with or without
** modification, are permitted provided that the following conditions
** are met:
** 
** 1. Redistributions of source code must retain the above copyright
**    notice, this list of conditions and the following disclaimer.
** 2. Redistributions in binary form must reproduce the above copyright
**    notice, this list of conditions and the following disclaimer in the
**    documentation and/or other materials provided with the distribution.
** 3. The name of the author may not be used to endorse or promote products
**    derived from this software without specific prior written permission.
** 
** THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
** IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
** OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
** IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
** INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
** NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
** DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
** THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
** (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
** THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
**/

/*
 * https://duo.com/docs/duoweb
 */

class CDuoWeb
{

    const DUO_PREFIX = "TX";
    const APP_PREFIX = "APP";
    const AUTH_PREFIX = "AUTH";

    const DUO_EXPIRE = 300;
    const APP_EXPIRE = 3600;

    const IKEY_LEN = 20;
    const SKEY_LEN = 40;
    const AKEY_LEN = 40; // if this changes you have to change ERR_AKEY

    const ERR_USER = 'ERR|The username passed to sign_request() is invalid.';
    const ERR_IKEY = 'ERR|The Duo integration key passed to sign_request() is invalid.';
    const ERR_SKEY = 'ERR|The Duo secret key passed to sign_request() is invalid.';
    const ERR_AKEY = 'ERR|The application secret key passed to sign_request() must be at least 40 characters.';

    private static function signVals($key, $vals, $prefix, $expire, $time = null)
    {
        $exp = ($time ? $time : time()) + $expire;
        $val = $vals . '|' . $exp;
        $b64 = base64_encode($val);
        $cookie = $prefix . '|' . $b64;

        $sig = hash_hmac("sha1", $cookie, $key);
        return $cookie . '|' . $sig;
    }

    private static function parseVals($key, $val, $prefix, $ikey, $time = null)
    {
        $ts = ($time ? $time : time());

        $parts = explode('|', $val);
        if (count($parts) !== 3) {
            return null;
        }
        list($u_prefix, $u_b64, $u_sig) = $parts;

        $sig = hash_hmac("sha1", $u_prefix . '|' . $u_b64, $key);
        if (hash_hmac("sha1", $sig, $key) !== hash_hmac("sha1", $u_sig, $key)) {
            return null;
        }

        if ($u_prefix !== $prefix) {
            return null;
        }

        $cookie_parts = explode('|', base64_decode($u_b64));
        if (count($cookie_parts) !== 3) {
            return null;
        }
        list($user, $u_ikey, $exp) = $cookie_parts;

        if ($u_ikey !== $ikey) {
            return null;
        }
        if ($ts >= intval($exp)) {
            return null;
        }

        return $user;
    }

    public static function signRequest($username, $time = null)
    {
        $config = select_config();
        $ikey = $config['2fa_duo_integration_key'];
        $skey = $config['2fa_duo_secret_key'];
        $akey = $config['2fa_duo_a_key'];

        if (!isset($username) || strlen($username) === 0) {
            return self::ERR_USER;
        }
        if (strpos($username, '|') !== false) {
            return self::ERR_USER;
        }
        if (!isset($ikey) || strlen($ikey) !== self::IKEY_LEN) {
            return self::ERR_IKEY;
        }
        if (!isset($skey) || strlen($skey) !== self::SKEY_LEN) {
            return self::ERR_SKEY;
        }
        if (!isset($akey) || strlen($akey) < self::AKEY_LEN) {
            return self::ERR_AKEY;
        }

        $vals = $username . '|' . $ikey;

        $duo_sig = self::signVals($skey, $vals, self::DUO_PREFIX, self::DUO_EXPIRE, $time);
        $app_sig = self::signVals($akey, $vals, self::APP_PREFIX, self::APP_EXPIRE, $time);

        return $duo_sig . ':' . $app_sig;
    }

    public static function verifyResponse($sig_response, $username, $time = null)
    {
        $config = select_config();
        $ikey = $config['2fa_duo_integration_key'];
        $skey = $config['2fa_duo_secret_key'];
        $akey = $config['2fa_duo_a_key'];

        list($auth_sig, $app_sig) = explode(':', $sig_response);

        $auth_user = self::parseVals($skey, $auth_sig, self::AUTH_PREFIX, $ikey, $time);
        $app_user = self::parseVals($akey, $app_sig, self::APP_PREFIX, $ikey, $time);

        if ($auth_user !== $app_user) {
            return null;
        }

        return $auth_user === $username;
    }
}
