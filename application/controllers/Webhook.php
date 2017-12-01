<?php defined('BASEPATH') OR exit('No direct script access allowed');
use \LINE\LINEBot;
use \LINE\LINEBot\HTTPClient\CurlHTTPClient;
use \LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use \LINE\LINEBot\MessageBuilder\StickerMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TemplateMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder;
use \LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder;
use \LINE\LINEBot\MessageBuilder\TemplateBuilder\ConfirmTemplateBuilder;
use \LINE\LINEBot\Event\MessageEvent\TextMessage;

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

    file_put_contents('php://stderr', 'Body: '.$body);

    if(is_array($this->events['events'])){
      foreach ($this->events['events'] as $event){
        // your code here

        // skip group and room event
        //if(! isset($event['source']['userId'])) continue;
 
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

      }
    }
    
    // debuging data
    file_put_contents('php://stderr', 'Body: '.$body);
  } // end of index.php

  private function start_game()
  {
    # code...
    // prepare button template
    $buttonTemplate = new ButtonTemplateBuilder("Kuis Dayatura", "Silahkan klik START untuk memulai permaian", "http://broadway-performance-systems.com/images/quick_start-1.jpg", ["MULAI","Ga Mau"]);
 
    // build message
    $messageBuilder = new TemplateMessageBuilder("Gunakan mobile app untuk melihat soal", $buttonTemplate);

    return $messageBuilder;
  }

  private function followCallback($event)
  {
    $res = $this->bot->getProfile($event['source']['userId']);
    if ($res->isSucceeded())
    {
      $profile = $res->getJSONDecodedBody();
 
      // create welcome message
      $message  = "Salam kenal, " . $profile['displayName'] . "!\n";
      $message .= "Silakan kirim pesan \"MULAI\" untuk memulai kuis.";
      $textMessageBuilder = new TextMessageBuilder($message);
 
      // create sticker message
      $stickerMessageBuilder = new StickerMessageBuilder(1, 3);
    
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
    $replyToken = $event['replyToken'];

    if(isset($event['source']['roomId']) || isset($event['source']['groupId'])) 
    {
      $roomId = (isset($event['source']['roomId'])) ? $event['source']['roomId'] : $event['source']['groupId'];
      
      switch ($userMessage) {
        
        case '.buat':
          if (!$this->undercovergame_m->getGame($roomId)) {
            $this->undercovergame_m->setGame($roomId);
            $message = 'Game Berhasil dibuat';
            $response = $this->bot->replyMessage($replyToken, 
                                                  new TextMessageBuilder($message));
          }else{
            $message = 'sudah ada game yang dibuat di room ini, silahkan bergabung';
            $response = $this->bot->replyMessage($replyToken, 
                                                  new TextMessageBuilder($message));
          }
          break;
        
        case '.join':

          $res = $this->bot->getProfile($event['source']['userId']);
          $profile = $res->getJSONDecodedBody();

          if ($this->undercovergame_m->getUser($event['source']['userId'])) 
          {
            if (!$this->undercovergame_m->checkPlayer($playerId, $roomId)) 
            {
              $response = $this->undercovergame_m->setPlayer($profile['userId'],$roomId);
              $message =  $profile['displayName'].' berhasil masuk ke permainan';
              $response = $this->bot->replyMessage($replyToken, 
                                                    new TextMessageBuilder($message));
            }else
            {
              $message = $profile['display_name'].' sudah masuk permainan';
              $response = $this->bot->replyMessage($replyToken, 
                                                    new TextMessageBuilder($message));
            }
          }else
          {
            $message = 'Yang belum add ga akan diwaro';
            $response = $this->bot->replyMessage($replyToken, 
                                                  new TextMessageBuilder($message));
          }
          
          break;
        
        case '.mulai':


          $message = 'Game akan segera dimulai, silahkan cek personal chat pada bot';
          $response = $this->bot->replyMessage($replyToken, 
                                                new TextMessageBuilder($message));
          break;

        case '.pemain':
          $message = 'Yang udah Join game: '.PHP_EOL.'Dayat';
          $response = $this->bot->replyMessage($replyToken, 
                                                new TextMessageBuilder($message));
          break;

        case '.batal':
          $this->undercovergame_m->deleteGame($roomId);
          $message = 'Permainan dibatalkan';
          $response = $this->bot->replyMessage($replyToken, 
                                                new TextMessageBuilder($message));
          break;

        case '.leave':
          if(isset($event['source']['roomId'])){
            
            $roomId = $event['source']['roomId'];
            $message = 'Terimakasih sudah bermain bersama kami.';
            $response = $this->bot->replyMessage($replyToken, 
                                                  new TextMessageBuilder($message));
            $this->undercovergame_m->deleteGame($roomId);
            $response = $this->bot->leaveRoom($roomId);
          }elseif (isset($event['source']['groupId'])) {
            $groupId = $event['source']['groupId'];
            $message = 'Terimakasih sudah bermain bersama kami.';
            $response = $this->bot->replyMessage($replyToken, 
                                                  new TextMessageBuilder($message));
            $this->undercovergame_m->deleteGame($roomId);
            $response = $this->bot->leaveGroup($groupId);
          }else{
            $message = 'Gila lu, mana tega gw ninggalin lu sendiri !!!';
            $response = $this->bot->replyMessage($replyToken, 
                                                  new TextMessageBuilder($message));
          }
          break;
            
        case '.buat':
          if (!$this->undercovergame_m->getPlayingGame($roomId)) {
            $this->undercovergame_m->setGame($roomId);
            $message = 'Game Berhasil dibuat';
            $response = $this->bot->replyMessage($replyToken, 
                                                  new TextMessageBuilder($message));
          }else{
            $message = 'sudah ada game yang dibuat di room ini, silahkan bergabung';
            $response = $this->bot->replyMessage($replyToken, 
                                                  new TextMessageBuilder($message));
          }
          break;

        case '.bantuan':
          $message = 'Game Berhasil dibuat';
          $response = $this->bot->replyMessage($replyToken, 
                                                new TextMessageBuilder($message));
          break;

        default:

          break;
      }
    }
    else
    {
      $message = 'Silahkan invite bot ke grup untuk memulai permaianan';
      $response = $this->bot->replyMessage($replyToken, 
                                            new TextMessageBuilder($message));
    }

    

  }

  private function stickerMessage($event)
  {
    // create sticker message
    $stickerMessageBuilder = new StickerMessageBuilder(1, 106);
 
    // create text message
    $message = 'Silakan kirim pesan "MULAI" untuk memulai kuis.';
    $textMessageBuilder = new TextMessageBuilder($message);
 
    // merge all message
    $multiMessageBuilder = new MultiMessageBuilder();
    $multiMessageBuilder->add($stickerMessageBuilder);
    $multiMessageBuilder->add($textMessageBuilder);
 
    // send message
    $this->bot->replyMessage($event['replyToken'], $multiMessageBuilder);
  }

  public function sendQuestion($replyToken, $questionNum=1)
  {
    // get question from database
    $question = $this->undercovergame_m->getQuestion($questionNum);
 
    // prepare answer options
    for($opsi = "a"; $opsi <= "d"; $opsi++) {
        if(!empty($question['option_'.$opsi]))
            $options[] = new MessageTemplateActionBuilder($question['option_'.$opsi], $question['option_'.$opsi]);
    }
 
    // prepare button template
    $buttonTemplate = new ButtonTemplateBuilder($question['number']."/10", $question['text'], $question['image'], $options);
 
    // build message
    $messageBuilder = new TemplateMessageBuilder("Gunakan mobile app untuk melihat soal", $buttonTemplate);
 
    // send message
    $response = $this->bot->replyMessage($replyToken, $messageBuilder);
  }

  private function checkAnswer($message, $replyToken)
  {
    // if answer is true, increment score
    if($this->undercovergame_m->isAnswerEqual($this->user['number'], $message)){
      $this->user['score']++;
      $this->undercovergame_m->setScore($this->user['user_id'], $this->user['score']);
    }
 
    if($this->user['number'] < 10)
    {
      // update number progress
     $this->undercovergame_m->setUserProgress($this->user['user_id'], $this->user['number'] + 1);
 
      // send next question
      $this->sendQuestion($replyToken, $this->user['number'] + 1);
    }
    else {
      // create user score message
      $message = 'Skormu '. $this->user['score'];
      $textMessageBuilder1 = new TextMessageBuilder($message);
 
      // create sticker message
      $stickerId = ($this->user['score'] < 8) ? 100 : 114;
      $stickerMessageBuilder = new StickerMessageBuilder(1, $stickerId);
 
      // create play again message
      $message = ($this->user['score'] < 8) ?
'Wkwkwk! Nyerah? Ketik "MULAI" untuk bermain lagi!':
'Great! Mantap bro! Ketik "MULAI" untuk bermain lagi!';
      $textMessageBuilder2 = new TextMessageBuilder($message);
 
      // merge all message
      $multiMessageBuilder = new MultiMessageBuilder();
      $multiMessageBuilder->add($textMessageBuilder1);
      $multiMessageBuilder->add($stickerMessageBuilder);
      $multiMessageBuilder->add($textMessageBuilder2);
 
      // send reply message
      $this->bot->replyMessage($replyToken, $multiMessageBuilder);
      $this->undercovergame_m->setUserProgress($this->user['user_id'], 0);
    }
  }
}