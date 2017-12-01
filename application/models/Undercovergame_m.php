<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Undercovergame_m extends CI_Model {

  function __construct(){
    parent::__construct();
    $this->load->database();
  }

  // Events Log
  function log_events($signature, $body)
  {
    $this->db->set('signature', $signature)
    ->set('events', $body)
    ->insert('eventlog');

    return $this->db->insert_id();
  }

  // Users
  function getUser($userId)
  {
    $data = $this->db->where('user_id', $userId)->get('users')->row_array();
    if(count($data) > 0) return $data;
    return false;
  }
 
  function saveUser($profile)
  {
    $this->db->set('user_id', $profile['userId'])
      ->set('display_name', $profile['displayName'])
      ->insert('users');
 
    return $this->db->insert_id();
  }

  // Question
  //function getQuestion($questionNum){}

  //function isAnswerEqual($number, $answer){}

  //function setUserProgress($user_id, $newNumber){}

  //function setScore($user_id, $score){}

}
