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
    $word = $this->db->select('word_a, word_b')
    ->from('words')
    ->where('id', $id)
    ->get();
    if(count($word->result())>0) return $word;
    return false;
  }

  function setGameWord($roomId, $civWord, $undWord) {
    $this->db->set('civilian_word', $civWord)
    ->set('undercover_word', $undWord)
    ->update('games');

    return $this->db->affected_rows();
  }

  function setPlayerWord($roomId, $role, $word) {
    $this->db->where('room_id', $roomId)
    ->where('role', $role)
    ->set('word', $word)
    ->update('players');

    return $this->db->affected_rows();
  }

  function countWord() {
    $word = $this->db->select('*')
    ->from('words')
    ->get();
    return count($word->num_rows())
  }

  // Roles
  function setRole($roomId, $userId, $role) {
    $this->db->where('room_id', $roomId)
    ->where('user_id', $userId)
    ->set('role', $role)
    ->update('players');

    return $this->db->affected_rows();
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
    ->update('games');

    return $this->db->affected_rows();
  }

  function deleteGame($roomId) {
    $this->db->where('room_id', $roomId)
    ->delete('games');

    return $this->db->affected_rows();
  }

  function getCivilianNumber($roomId, $civilian) {
    $civilian = $this->db->select('civilian_tot')
    ->from('games')
    ->where('room_id', $roomId)
    ->get();
    if(count($civilian->result())>0) return $civilian;
    return false;
  }

  function updateCivilianNumber($roomId, $civilian) {
    $this->db->where('room_id', $roomId)
    ->set('civilian_tot', $civilian)
    ->update('games');

    return $this->db->affected_rows();
  }

  function getUndercoverNumber($roomId, $undercover) {
    $undercover = $this->db->select('undercover_tot')
    ->from('games')
    ->where('room_id', $roomId)
    ->get();
    if(count($undercover->result())>0) return $undercover;
    return false;
  }

  function updateUndercoverNumber($roomId, $undercover) {
    $this->db->where('room_id', $roomId)
    ->set('undercover_tot', $undercover)
    ->update('games');

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
    $this->db->set('room_id', $roomId)
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
    $player = $this->db->select('*')
    ->from('players')
    ->where('room_id', $roomId)
    ->get();
    if(count($player->result())>0) return $player;
    return false;
  }
}