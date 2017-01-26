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
//
// Este bloco é parte do Moodle Provas - http://tutoriais.moodle.ufsc.br/provas/
// Este projeto é financiado pela
// UAB - Universidade Aberta do Brasil (http://www.uab.capes.gov.br/)
// e é distribuído sob os termos da "GNU General Public License",
// como publicada pela "Free Software Foundation".

/**
 * This file contains sample of CD configuration for an institution
 *
 * @package    local_exam_authorization
 * @author     Antonio Carlos Mariani
 * @copyright  2010 onwards Universidade Federal de Santa Catarina (http://www.ufsc.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

$headers = \local_exam_authorization\authorization::get_http_headers();

if (!empty($headers->version) && $headers->version >= 3.3) {
    echo '{ "exam_description":"Moodle Exam",
            "institution_acronym":"MyInst",
            "institution_name":"My Institution",
            "contact_email":"contact@myinst.ddd.kk",

            "exam_server_url":"http://<moodle_url>",
            "send_data_path":"/local/exam_authorization/receive_data.php",

            "allowed_tcp_out_ipv4":"150.162.255.173#443 150.162.255.177#443 150.162.255.233#80",
            "allowed_tcp_out_ipv6":"2801:84:0:2::10#80 2801:84:0:2::10#443"
          }';
};
