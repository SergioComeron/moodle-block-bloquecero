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
 * 
 * Settings for validador block
 * @package   block_bloquecero
 * @copyright  2025 Sergio Comerón (info@sergiocomeron.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
        'block_bloquecero/client_id',
        get_string('clientid', 'block_bloquecero'),
        get_string('clientid_desc', 'block_bloquecero'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'block_bloquecero/client_secret',
        get_string('clientsecret', 'block_bloquecero'),
        get_string('clientsecret_desc', 'block_bloquecero'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'block_bloquecero/token_url',
        get_string('tokenurl', 'block_bloquecero'),
        get_string('tokenurl_desc', 'block_bloquecero'),
        '',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'block_bloquecero/scope',
        get_string('scope', 'block_bloquecero'),
        get_string('scope_desc', 'block_bloquecero'),
        '',
        PARAM_TEXT
    ));
}