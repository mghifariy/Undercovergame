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
  
  function __construct() {
    parent::__construct();
    $this->load->model('undercovergame_m');

    // create bot object
    $httpClient = new CurlHTTPClient($_ENV['CHANNEL_ACCESS_TOKEN']);
    $this->bot  = new LINEBot($httpClient, ['channelSecret' => $_ENV['CHANNEL_SECRET']]);
  }

  public function index() {
    if($_SERVER['REQUEST_METHOD'] !== 'POST') {
      echo "Oops! What are you looking for?";
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

    if(is_array($this->events['events'])) {
      foreach ($this->events['events'] as $event) {
        // get user data from database
        $this->user = $this->undercovergame_m->getUser($event['source']['userId']);
 
        // if user not registered
        if(!$this->user) $this->followCallback($event);
        else {
          // respond event
          if($event['type'] == 'message') {
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
    
    file_put_contents('php://stderr', 'Body: '.$body); // debuging data
  }

  private function start_game() {
    # code...
    // prepare button template
    $buttonTemplate = new ButtonTemplateBuilder("Kuis Dayatura", "Silahkan klik START untuk memulai permaian", "http://broadway-performance-systems.com/images/quick_start-1.jpg", ["MULAI","Ga Mau"]);
 
    // build message
    $messageBuilder = new TemplateMessageBuilder("Gunakan mobile app untuk melihat soal", $buttonTemplate);

    return $messageBuilder;
  }

  private function followCallback($event) {
    $res = $this->bot->getProfile($event['source']['userId']);
    
    if($res->isSucceeded()) {
      $profile = $res->getJSONDecodedBody();
 
      // create welcome message
      $message  = "Halo, " . $profile['displayName'] . "!\n";
      $message .= "Silahkan invite saya ke grup atau multichat untuk mulai bermain Undercover^^";
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

  private function textMessage($event) {
    $userMessage = $event['message']['text'];
    $replyToken = $event['replyToken'];

    if(isset($event['source']['roomId']) || isset($event['source']['groupId'])) {
      $roomId = (isset($event['source']['roomId'])) ? $event['source']['roomId'] : $event['source']['groupId'];
      
      switch ($userMessage) {
        case '.buat':
          $res = $this->bot->getProfile($event['source']['userId']);
          $profile = $res->getJSONDecodedBody();

          if(isset($profile['displayName'])) {
            if(!$this->undercovergame_m->getGame($roomId)) {
              $this->undercovergame_m->setGame($roomId);
              $message = 'Game berhasil dibuat! Silahkan bergabung.';
              $response = $this->bot->replyMessage($replyToken, 
                                                    new TextMessageBuilder($message));
            }
            else {
              $message = 'Game sudah dibuat sebelumnya! Silahkan bergabung.';
              $response = $this->bot->replyMessage($replyToken, 
                                                    new TextMessageBuilder($message));
            }
          }
          else {
            $message = $profile['displayName'] . ' tidak bisa menggunakan perintah bot karena belum menambahkan sebagai teman.';
            $response = $this->bot->replyMessage($replyToken, 
                                                    new TextMessageBuilder($message));
          }
          break;
        
        case '.join':
          if($this->undercovergame_m->getGame($roomId)) {
            $res = $this->bot->getProfile($event['source']['userId']);
            $profile = $res->getJSONDecodedBody();

            if(isset($profile['displayName'])) {
              if(!$this->undercovergame_m->checkPlayer($event['source']['userId'], $roomId)) {
                $response = $this->undercovergame_m->setPlayer($profile,$roomId);
                $message =  $profile['displayName'] . ' berhasil bergabung.';
                $response = $this->bot->replyMessage($replyToken, 
                                                      new TextMessageBuilder($message));
              }
              else {
                $message = $profile['displayName'] . ' sudah bergabung.';
                $response = $this->bot->replyMessage($replyToken, 
                                                      new TextMessageBuilder($message));
              }
            }
            else {
              $message = $profile['displayName'] . ' tidak bisa menggunakan perintah bot karena belum menambahkan sebagai teman.';
              $response = $this->bot->replyMessage($replyToken, 
                                                      new TextMessageBuilder($message));
            }
          }
          else {
            $message = 'Game belum dibuat! Ketik .buat untuk membuat game baru.';
            $response = $this->bot->replyMessage($replyToken, 
                                                    new TextMessageBuilder($message));
          }
          break;
        
        case '.mulai':
          $res = $this->bot->getProfile($event['source']['userId']);
          $profile = $res->getJSONDecodedBody();

          if (isset($profile['displayName'])) 
          {
            if ($this->undercovergame_m->getGame($roomId))
            {
              if(!$this->undercovergame_m->getPlayinggame($roomId)){
                $jumlahPemain = $this->undercovergame_m->getPlayer($roomId)->num_rows();
                $minimalPlayer = 2;
                if ($jumlahPemain < $minimalPlayer) {
                  $message = 'Jumlah Pemain Minimal 4 orang';
                  $response = $this->bot->replyMessage($replyToken, 
                                                        new TextMessageBuilder($message));
                }
                else 
                {
  
                  //random player role
                  $jumlahUndercover = rand(1,($jumlahPemain/2)-1);
                  $jumlahCivilian = $jumlahPemain - $jumlahUndercover;
  
                  $this->undercovergame_m->updateUndercoverNumber($roomId,$jumlahUndercover);
                  $this->undercovergame_m->updateCivilianNumber($roomId,$jumlahCivilian);
                  
                  $pemain = $this->undercovergame_m->getPlayer($roomId)->result();

                  foreach ($pemain as $player) 
                  {
                    $userId = $player->user_id;
                    if ((rand()%2 == 1 && $jumlahCivilian != 0) || $jumlahUndercover == 0) {
                      $this->undercovergame_m->setRole($roomId,$userId,"civilian");
                      $jumlahCivilian -= 1;
                    }else {
                      $this->undercovergame_m->setRole($roomId,$userId,"undercover");
                      $jumlahCivilian -= 1;
                    }
                  }
  
                  //Random player word
                  $word = $this->undercovergame_m->getWord(rand(1,$this->undercovergame_m->countWord()))->result();
                  $civilianWord = '';
                  $undercoverWord = '';
                  if (rand()%2 == 1) {
                    $civilianWord = $word->word_a;
                    $undercoverWord = $word->word_b;
                  }else {
                    $civilianWord = $word->word_b;
                    $undercoverWord = $word->word_a;
                  }
                  echo $civilianWord.'='.$word->word_a.' '.$undercoverWord.'='.$word->word_b;
  
                  $this->undercovergame_m->setGameWord($roomId,$civilianWord,$undercoverWord);
                  // $pemain = $this->undercovergame_m->getPlayer($roomId)->result();
                  foreach ($pemain as $player) 
                  {
                    if ($player->role == 'undercover') 
                    {
                      $this->undercovergame_m->setPlayerWord($roomId,'undercover',$undercoverWord);
                    }
                    else
                    {
                      $this->undercovergame_m->setPlayerWord($roomId,'civilian',$civilianWord);
                    }
                    $this->undercovergame_m->setPlayerPlaying($roomId,$player->user_id,'true');
                  }
                  

                  $this->undercovergame_m->setPlayingGame($roomId,'true');
  
                  
                  $message = 'Game akan segera dimulai, silahkan cek personal chat pada bot';
                  $response = $this->bot->replyMessage($replyToken, 
                                                        new TextMessageBuilder($message));
                }  
              }
              else
              {
                $message = 'Game sudah berjalan, silahkan ikut di game selanjutnya';
                $response = $this->bot->replyMessage($replyToken, 
                                                      new TextMessageBuilder($message));
              }
            }else {
              # code...
              $message = 'Belum ada game yang dibuat. Silahkana buat terlebih dahulu :3';
                $response = $this->bot->replyMessage($replyToken, 
                                                      new TextMessageBuilder($message));
            }
          }
          else
          {
            $message = 'Yang belum add ga akan diwaro';
            $response = $this->bot->replyMessage($replyToken, 
                                                  new TextMessageBuilder($message));
          }
          break;

        case '.pemain':

          $res = $this->bot->getProfile($event['source']['userId']);
          $profile = $res->getJSONDecodedBody();

          if(isset($profile['displayName'])) 
          {
            
            $pemain = $this->undercovergame_m->getPlayer($roomId)->result();
            //$players = $pemain->getJSONDecodedBody();
            $message = 'Yang udah Join game: '.PHP_EOL.'Dayat';

            foreach ($pemain as $player) {
              $message = $message.PHP_EOL.$player->display_name;
            }

            $response = $this->bot->replyMessage($replyToken, 
                                                  new TextMessageBuilder($message));
          }
          else
          {
            $message = 'Yang belum add ga akan diwaro';
            $response = $this->bot->replyMessage($replyToken, 
                                                  new TextMessageBuilder($message));
          }
          break;

        case '.batal':

          $res = $this->bot->getProfile($event['source']['userId']);
          $profile = $res->getJSONDecodedBody();

          if(isset($profile['displayName'])) 
          {
            if($this->undercovergame_m->getGame($roomId)) {
              $this->undercovergame_m->resetPlayer($roomId);
              $this->undercovergame_m->deleteGame($roomId);
              $message = 'Permainan dibatalkan';
              $response = $this->bot->replyMessage($replyToken, 
                                                  new TextMessageBuilder($message));
            }
            else{
              $message = 'Tidak ada permainan di grup ini';
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

        case '.leave':

          $res = $this->bot->getProfile($event['source']['userId']);
          $profile = $res->getJSONDecodedBody();

          if(isset($profile['displayName'])) 
          {

            
            if(isset($event['source']['roomId'])){
              
              $roomId = $event['source']['roomId'];
              $message = 'Terimakasih sudah bermain bersama kami.';
              $response = $this->bot->replyMessage($replyToken, 
              new TextMessageBuilder($message));
              
            }elseif(isset($event['source']['groupId'])) {
              $groupId = $event['source']['groupId'];
              $message = 'Terimakasih sudah bermain bersama kami.';
              $response = $this->bot->replyMessage($replyToken, 
              new TextMessageBuilder($message));
              
            }else{
              $message = 'Gila lu, mana tega gw ninggalin lu sendiri !!!';
              $response = $this->bot->replyMessage($replyToken, 
              new TextMessageBuilder($message));
            }
            $this->undercovergame_m->resetPlayer($roomId);
            $this->undercovergame_m->deleteGame($roomId);
            $this->bot->leaveGroup($groupId);
            
          }else
          {
            $message = 'Yang belum add ga akan diwaro';
            $response = $this->bot->replyMessage($replyToken, 
                                                  new TextMessageBuilder($message));
          }

          break;
            
        case '.buat':

          $res = $this->bot->getProfile($event['source']['userId']);
          $profile = $res->getJSONDecodedBody();

          if(isset($profile['displayName'])) 
          {


          if(!$this->undercovergame_m->getPlayingGame($roomId)) {
            $this->undercovergame_m->setGame($roomId);
            $message = 'Game Berhasil dibuat';
            $response = $this->bot->replyMessage($replyToken, 
                                                  new TextMessageBuilder($message));
          }else{
            $message = 'sudah ada game yang dibuat di room ini, silahkan bergabung';
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

        case '.bantuan':

          $res = $this->bot->getProfile($event['source']['userId']);
          $profile = $res->getJSONDecodedBody();

          if(isset($profile['displayName'])) 
          {

            $message = 'Game Berhasil dibuat';
            $response = $this->bot->replyMessage($replyToken, 
                                                  new TextMessageBuilder($message));
            

          }else
          {
            $message = 'Yang belum add ga akan diwaro';
            $response = $this->bot->replyMessage($replyToken, 
                                                  new TextMessageBuilder($message));
          }
          break;

        default:

          break;
      }
    }
    else
    {
      $message = 'Perintah hanya bisa dilakukan di grup';
      $response = $this->bot->replyMessage($replyToken, 
                                            new TextMessageBuilder($message));
    }

    

  }
}