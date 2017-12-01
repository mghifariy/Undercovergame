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

  function getGame($room) {
    $status = $this->db->where('room_id', $room)->get('games')->row_array();
    if(count($status)>0) return true;
    return false;
  }

  function getPlayingGame($room) {
    $status = $this->db->where('playing', 'true')
    ->where('room_id', $room)
    ->get('games')->row_array();
    if(count($status)>0) return true;
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
  function checkPlayer($userId, $room_id) {
    $playing = $this->db->where('user_id', $userId)
    ->where('room_id', $room_id)
    ->get('players')->row_array();
    if(count($playing)>0) return true;
    return false;
  }

  function setPlayer($userId, $room_id) {
    $this->db->where('user_id', $userId)
    ->set('room_id', $room_id)
    ->update('players');

    return $this->db->affected_rows();
  }

  function playerStart($userId, $room_id) {
    $this->db->where('user_id', $userId)
    ->where('room_id', $room_id)
    ->set('playing', 'true')
    ->update('players');

    return $this->db->affected_rows();
  }
}