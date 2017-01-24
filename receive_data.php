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
 * A script to receive data from CD.
 *
 * Obtém dados da rede local e funciona também como keep-alive.
 *
 * @package    local_exam_authorization
 * @author     Antonio Carlos Mariani
 * @copyright  2010 onwards Universidade Federal de Santa Catarina (http://www.ufsc.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

if (\local_exam_authorization\authorization::check_ip_header(false, true) &&
        \local_exam_authorization\authorization::check_network_header(false, true)) {
    $real_ip = \local_exam_authorization\authorization::get_remote_addr();
    $rec = new \stdClass();
    $rec->timemodified = time();

    $headers = \local_exam_authorization\authorization::get_http_headers();

    $params = array('ip'=>$headers->ip, 'network'=>$headers->network, 'real_ip'=>$real_ip);
    if ($id = $DB->get_field('exam_client_hosts', 'id', $params)) {
        $rec->id = $id;
        $DB->update_record('exam_client_hosts', $rec);
    } else {
        $rec->ip      = $headers->ip;
        $rec->network = $headers->network;
        $rec->real_ip = $real_ip;
        $DB->insert_record('exam_client_hosts', $rec);
    }
    http_response_code(200);
} else {
    http_response_code(412);
}
