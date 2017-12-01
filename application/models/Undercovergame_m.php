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

  //Roles
  function getRole($userId) {
    $civilian = $this->db->where('id', 1)
      ->get('roles')
      ->row_array();
    $undercover = $this->db->where('id', 2)
      ->get('roles')
      ->row_array();
    $mrwhite = $this->db->where('id', 3)
      ->get('roles')
      ->row_array();

    // Randomize Role
    
  }

  // Words
  function getWord($role) {
    $data = $this->db->get('words')->row_array();
    if(count($data)>0) return $data;
    return false;

    // Randomize Word
    
  }

}