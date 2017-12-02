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
  function setGame($roomId) {
    $this->db->set('room_id', $roomId)
    ->insert('games');

    return $this->db->insert_id();
  }

  function getGame($roomId) {
    $status = $this->db->where('room_id', $roomId)->get('games')->row_array();
    if(count($status)>0) return true;
    return false;
  }

  function getPlayingGame($roomId) {
    $status = $this->db->where('playing', 'true')
    ->where('room_id', $roomId)
    ->get('games')->row_array();
    if(count($status)>0) return true;
    return false;
  }

  function setPlayingGame($roomId, $status) {
    $this->db->where('room_id', $roomId)
    ->set('playing', $status)
    ->insert('games');

    return $this->db->insert_id();
  }

  function deleteGame($roomId) {
    $this->db->where('room_id', $roomId)
    ->delete('games');

    return $this->db->affected_rows();
  }

  // Players
  function checkPlayer($userId, $roomId) {
    $playing = $this->db->where('user_id', $userId)
    ->where('room_id', $roomId)
    ->get('players')->row_array();
    if(count($playing)>0) return true;
    return false;
  }

  function setPlayer($profile, $roomId) {
    $this->db
    ->set('room_id', $roomId)
    ->set('user_id', $profile['userId'])
    ->set('display_name', $profile['displayName'])
    ->insert('players');

    return $this->db->insert_id();
  }

  function resetPlayer($roomId) {
    $this->db->where('room_id', $roomId)
    ->delete('players');

    return $this->db->affected_rows();
  }

  function playerStart($userId, $roomId) {
    $this->db->where('user_id', $userId)
    ->where('room_id', $roomId)
    ->set('playing', 'true')
    ->update('players');

    return $this->db->affected_rows();
  }

  function getPlayer($roomId) {
    $player = $this->db->where('room_id', $roomId)
    ->get('players')->row_array();
    if(count($player)>0) return true;
    return false;
  }
}