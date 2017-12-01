<?php defined('BASEPATH') OR exit('No direct script access allowed');

use \LINE\LINEBot;
use \LINE\LINEBot\HTTPClient\CurlHTTPClient;
use \LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use \LINE\LINEBot\MessageBuilder\StickerMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TemplateMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder;
use \LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder;

class Webhook extends CI_Controller {

  private $bot;
  private $events;
  private $signature;
  private $user;

  function __construct()
  {
    parent::__construct();
    $this->load->model('undercovergame_m');

    // create bot object
    $httpClient = new CurlHTTPClient($_ENV['CHANNEL_ACCESS_TOKEN']);
    $this->bot  = new LINEBot($httpClient, ['channelSecret' => $_ENV['CHANNEL_SECRET']]);
  }

  public function index()
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      echo "Hello Coders!";
      header('HTTP/1.1 400 Only POST method allowed');
      exit;
    }

    // get request
    $body = file_get_contents('php://input');
    $this->signature = isset($_SERVER['HTTP_X_LINE_SIGNATURE']) ? $_SERVER['HTTP_X_LINE_SIGNATURE'] : "-";
    $this->events = json_decode($body, true);

    // log every event requests
    $this->undercovergame_m->log_events($this->signature, $body);

    if(is_array($this->events['events'])){
      foreach ($this->events['events'] as $event){
 
        // skip group and room event
        if(! isset($event['source']['userId'])) continue;
 
        // get user data from database
        $this->user = $this->undercovergame_m->getUser($event['source']['userId']);
 
        // if user not registered
        if(!$this->user) $this->followCallback($event);
        else {
          // respond event
          if($event['type'] == 'message'){
            if(method_exists($this, $event['message']['type'].'Message')){
              $this->{$event['message']['type'].'Message'}($event);
            }
          } else {
            if(method_exists($this, $event['type'].'Callback')){
              $this->{$event['type'].'Callback'}($event);
            }
          }
        }
 
      } // end of foreach
      // debuging data
    file_put_contents('php://stderr', 'Body: '.$body);
    }

  } // end of index.php

  private function followCallback($event)
  {
    $res = $this->bot->getProfile($event['source']['userId']);
    if ($res->isSucceeded())
    {
      $profile = $res->getJSONDecodedBody();
 
      // create welcome message
      $message  = "Terimakasih sudah menambahkan sebagai teman, " . $profile['displayName'] . "!\n";
      $message  = "Kirim pesan \\buat untuk membuat permainan.";
      $textMessageBuilder = new TextMessageBuilder($message);
 
      // create sticker message
      //$stickerMessageBuilder = new StickerMessageBuilder(1, 3);
 
      // merge all message
      $multiMessageBuilder = new MultiMessageBuilder();
      $multiMessageBuilder->add($textMessageBuilder);
      $multiMessageBuilder->add($stickerMessageBuilder);
 
      // send reply message
      $this->bot->replyMessage($event['replyToken'], $multiMessageBuilder);
 
      // save user data
      $this->undercovergame_m->saveUser($profile);
    }
  }

  private function textMessage($event)
  {
    $userMessage = $event['message']['text'];
    if(strtolower($userMessage) == '/buat')
    {
      $message = $userMessage;
      $textMessageBuilder = new TextMessageBuilder($message);
      $this->bot->replyMessage($event['replyToken'], $textMessageBuilder);

    } else {
      $message = 'Kirim pesan \buat untuk membuat permainan.';
      $textMessageBuilder = new TextMessageBuilder($message);
      $this->bot->replyMessage($event['replyToken'], $textMessageBuilder);
    }

    // if user join
    if(strtolower($userMessage) == '/join')
    {
      $message  = $profile['displayName'] . " berhasil bergabung.";
    }
  }

  //private function stickerMessage($event){}

    public function askPlayer($replyToken)
    {
      // get question from database
      //$question = $this->tebakkode_m->getQuestion($questionNum);
      $message  = "Berapa jumlah undercover ?";
   
      // prepare answer options
      for($opsi = 4; $opsi <= 10; $opsi++) {
          if(!empty($question['option_'.$opsi]))
              $options[] = new MessageTemplateActionBuilder($question[$opsi], $question[$opsi]);
      }
   
      // prepare button template
      $buttonTemplate = new ButtonTemplateBuilder($question['text']);
   
      // build message
      $messageBuilder = new TemplateMessageBuilder("Silakan cek hp untuk melihat pesan.", $buttonTemplate);
   
      // send message
      $response = $this->bot->replyMessage($replyToken, $messageBuilder);
    }

  //private function checkAnswer($message, $replyToken){}

}
