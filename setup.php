<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Common setup.
 *
 * @package    auth_saml2
 * @copyright  Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use auth_saml2\event\cert_regenerated;

require_once(__DIR__ . '/setuplib.php');

global $CFG, $saml2auth;

// Tell SSP that we are on 443 if we are terminating SSL elsewhere.
if (isset($CFG->sslproxy) && $CFG->sslproxy) {
      $_SERVER['SERVER_PORT'] = '443';
}

$saml2auth = new auth_plugin_saml2();

// Auto create unique certificates for this moodle SP.
//
// This is one area which many SSP instances get horridly wrong and leave the
// default certificates which is very insecure. Here we create a customized
// cert/key pair just-in-time. If for some reason you do want to use existing
// files then just copy them over the files in /sitedata/saml2/.
$saml2auth->get_saml2_directory(); // It will create it if needed.
$missingcertpem = !file_exists($saml2auth->certpem);
$missingcertcrt = !file_exists($saml2auth->certcrt);
if ($missingcertpem || $missingcertcrt) {
    // Could not find one or both certificates. Log an error.
    $errorstring = "";
    $missingcertpem ? $errorstring .= "= Missing cert pem file! =\n" : null;
    $missingcertcrt ? $errorstring .= "= Missing cert crt file! = \n" : null;
    $errorstring .= "Now regenerating saml2 certificates...";
    // @codingStandardsIgnoreStart
    if (!PHPUNIT_TEST) { // Don't clutter the unit test output with this error_log message.
        error_log($errorstring);
    }
    // @codingStandardsIgnoreEnd
    cert_regenerated::create(['other' => ['reason' => $errorstring]])->trigger();

    $error = '';
    try {
        create_certificates($saml2auth);
    } catch (saml2_exception $exception) {
        $error = $exception->getMessage() . $exception->getTraceAsString();
    }

    if ($error && !PHPUNIT_TEST) { // Don't clutter the unit test output with this error_log message.
        // @codingStandardsIgnoreStart
        error_log($error);
        // @codingStandardsIgnoreEnd
    }
}

SimpleSAML\Configuration::setConfigDir("$CFG->dirroot/auth/saml2/config");
