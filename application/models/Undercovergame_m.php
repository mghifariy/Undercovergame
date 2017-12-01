<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Undercovergame_m extends CI_Model {

  function __construct() {
    parent::__construct();
    $this->load->database();
  }

  // Events Log
  function log_events($signature, $body) {
    $this->db->set('signature', $signature)
    ->set('events', $body)
    ->insert('eventlog');

    return $this->db->insert_id();
  }

  // Users
  function getUser($userId) {
    $data = $this->db->where('user_id', $userId)->get('users')->row_array();
    if(count($data) > 0) return $data;
    return false;
  }
 
  function saveUser($profile) {
    $this->db->set('user_id', $profile['userId'])
      ->set('display_name', $profile['displayName'])
      ->insert('users');
      
    return $this->db->insert_id();
  }

  // Words
  function getWord($id) {
    $data = $this->db->where('id', $id)->get('words')->row_array();
    if(count($data)>0) return $data;
    return false;
  }

  // Games
  function setGame($room) {
    $this->db->set('room_id', $room)
    ->insert('games');

    return $this->db->insert_id();
  }

  function getPlayingGame($room) {
    $status = $this->db->where('playing', 'true')->get('games')->row_array();
    if($status == 'true') return $status;
    return false;
  }

  function setPlayingGame($room, $status) {
    $this->db->where('room_id', $room)
    ->set('playing', $status)
    ->insert('games');

    return $this->db->insert_id();
  }

  function deleteGame($room) {
    $this->db->where('room_id', $room)
    ->delete('games');

    return $this->db->affected_rows();
  }

  // Player
  function updateCivilian($room_id, $total) {
    $this->db->where('id', $room_id)
    ->set('civilian_tot', $total)
    ->update('games');

    return $this->db->affected_rows();
  }


}