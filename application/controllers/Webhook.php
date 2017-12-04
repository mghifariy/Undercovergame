<?php defined('BASEPATH') OR exit('No direct script access allowed');
use \LINE\LINEBot;
use \LINE\LINEBot\HTTPClient\CurlHTTPClient;
use \LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use \LINE\LINEBot\MessageBuilder\StickerMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TemplateMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder;
use \LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder;
use LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder;
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
    $playerPlayingStatus = true;

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
                $minimalPlayer = 1;
                
                if ($jumlahPemain < $minimalPlayer) {
                  $message = 'Jumlah Pemain Minimal 4 orang';
                  $response = $this->bot->replyMessage($replyToken, 
                                                        new TextMessageBuilder($message));
                }
                else 
                {
  
                  //random player role
                  $jumlahUndercover = rand(1,($jumlahPemain/2));
                  echo $jumlahUndercover;
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
                      $jumlahUndercover -= 1;
                    }
                  }
                  
                  //Random player word
                  $jumlahKata = $this->undercovergame_m->countWord()->num_rows();
                  $indexKata = rand(1,$jumlahKata);
                  $word = $this->undercovergame_m->getWord($indexKata)->result();
                  $kata1='';
                  $kata2='';
                  foreach ($word as $kata) {
                    $kata1 = $kata->word_a;
                    $kata2 = $kata->word_b;
                  }
                  $civilianWord = '';
                  $undercoverWord = '';
                  
                  if (rand(10)%2 == 1) {
                    $civilianWord = $kata1;
                    $undercoverWord = $kata2;
                  }else {
                    $civilianWord = $kata2;
                    $undercoverWord = $kata1;
                  }

                  //set word
                  $this->undercovergame_m->setGameWord($roomId,$civilianWord,$undercoverWord);
                  $this->undercovergame_m->setPlayerWord($roomId,'civilian',$civilianWord);
                  $this->undercovergame_m->setPlayerWord($roomId,'undercover',$undercoverWord);
                  
                  //start game
                  $this->undercovergame_m->setPlayingGame($roomId,'true');
                  
                  //push message to player
                  $pemain = $this->undercovergame_m->getPlayer($roomId)->result();
                  foreach ($pemain as $player) {
                    $message = 'KATA kamu adalah '.$player->word;
                    $this->bot->pushMessage($player->user_id, new TextMessageBuilder($message));
                  }


                  $message = '[PERMAINAN DIMULAI]'.PHP_EOL.PHP_EOL;
                  $message.= 'Silahkan sebutkan pentunjuk katamu di grup ini'.PHP_EOL.PHP_EOL;
                  $message.= 'Untuk melakukan vote silahkan cek personal chat pada bot';
                  $response = $this->bot->replyMessage($replyToken, 
                                                        new TextMessageBuilder($message));


                  // voting

                  $pemain = $this->undercovergame_m->getPlayer($roomId)->result();
                  $judul = 'Vote eksekusi';
                  $kalimat = 'Silahkan pilih pemain yang akan dieksekusi';
                  $playerButtons = [];
                  $i = 0;
                  foreach ($pemain as $player) {
                    $playerButtons[$i] = new PostbackTemplateActionBuilder($player->display_name, $player->user_id);
                    $i++;
                  }

                  foreach ($pemain as $player) {
                    $this->undercovergame_m->setPlayerPlaying($roomId,$player->user_id,'true');
                    $imageUrl = 'https://cdn.dribbble.com/users/881160/screenshots/2152292/undercover-icon.png';
                    $buttonTemplateBuilder = new ButtonTemplateBuilder(
                        $judl,
                        $kalimat,
                        $imageUrl,
                        $playerButtons
                        // [
                        //     new PostbackTemplateActionBuilder('Buy', 'action=buy&itemid=123'),
                        //     new MessageTemplateActionBuilder('Say message', 'hello hello'),
                        // ]
                    );
                    $templateMessage = new TemplateMessageBuilder('Cek pesan pada smartphone', $buttonTemplateBuilder);
          
                    $response = $this->bot->pushMessage($player->user_id, $templateMessage);
                  }


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
            
            //sudah ada game
            if($this->undercovergame_m->getGame($roomId)) {
              
              $pemain = $this->undercovergame_m->getPlayer($roomId)->result();
              //$players = $pemain->getJSONDecodedBody();
              $message = 'Yang udah Join game: '.PHP_EOL;
  
              foreach ($pemain as $player) {
                $message = $message.PHP_EOL.$player->display_name;
              }
  
              $response = $this->bot->replyMessage($replyToken, 
                                                    new TextMessageBuilder($message));

            }
            else {
              $message = 'Belum ada game yang dibuat, silahkan buat terlebih dahulu.';
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
            
        

        case '.bantuan':

          $res = $this->bot->getProfile($event['source']['userId']);
          $profile = $res->getJSONDecodedBody();

          if(isset($profile['displayName'])) 
          {

            $message = 'Perintah yang dapat digunakan'.PHP_EOL;
            $message .= '.buat = Membuat game'.PHP_EOL;
            $message .= '.join = Bergabung dalam permaian'.PHP_EOL;
            $message .= '.mulai = Memulai permaian'.PHP_EOL;
            $message .= '.pemain = Memunculkan nama permain'.PHP_EOL;
            $message .= '.batal = Keluar dari permainan'.PHP_EOL;
            $message .= '.leave = Mengeluarkan bot dari ruangan'.PHP_EOL;
            $message .= '.bantuan = Menampilkan perintah dasar'.PHP_EOL;
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
    elseif($playerPlayingStatus)
    {
      $message = 'anda sedang tergabung dalam permainan';
      $response = $this->bot->replyMessage($replyToken, 
                                            new TextMessageBuilder($message));
    }
    else
    {
      $message = 'Perintah hanya bisa dilakukan di grup';
      $response = $this->bot->replyMessage($replyToken, 
                                            new TextMessageBuilder($message));
    }
  }

  private function postbackCallback($event)
  { 

    $userId = $event['source']['userId'];
    $userGroupId = null;
    $votedUserId = $event['postback']['data'];
    $votedUserGroupId = null;
    $votedUserNum = 1;
    $replyToken = $event['replyToken'];

    $voted = null; 
    //get data yang mem vote
    $pemainVote = $this->undercovergame_m->getPlayerById($userId)->result();
    foreach ($pemainVote as $player) {
      $userGroupId = $player->room_id;
      $voted = $player->voted;
    }
    
    // if(!$voted)
    // {

      //get data yang divote
      $pemainVoted = $this->undercovergame_m->getPlayerById($votedUserId)->result();
      foreach ($pemainVoted as $player) {
        $votedUserGroupId = $player->room_id;
        $votedUserNum += $player->vote_num;
      }
      
      $status = '';
      $jumlahPemain = 0;
      $jumlahVote = 0;
      //lakukan vote
      $this->undercovergame_m->vote($votedUserId, $votedUserGroupId, $votedUserNum);
      
      
      $pemain = $this->undercovergame_m->getPlayer($userGroupId)->result();
      foreach ($pemain as $player) {
        if ($player->playing) {
          $jumlahPemain++;
          if ($player->voted) {
            $jumlahVote++;
          }
        }
        $status .= $player->voted.$player->display_name.$player->playing.PHP_EOL.$jumlahPemain.$jumlahVote;
      }

      // //DEBUG
      echo PHP_EOL.$status;

      $status = '';
      $jumlahPemain = 0;
      $jumlahVote = 0;

      //ubah status vote jadi true
      $this->undercovergame_m->votedStatus($userId, $userGroupId, 'true');
      
      

      $pemain = $this->undercovergame_m->getPlayer($userGroupId)->result();
      foreach ($pemain as $player) {
        if ($player->playing) {
          $jumlahPemain++;
          if ($player->voted) {
            $jumlahVote++;
          }
        }
        $status .= $player->voted.$player->display_name.$player->playing.PHP_EOL.$jumlahPemain.$jumlahUndercover;
      }

      // //DEBUG
      echo PHP_EOL.$status;
      

      $message = 'Vote anda berhasil dilakukan';
      $message .= PHP_EOL.$status;
      // echo $message;
      $response = $this->bot->replyMessage($replyToken, 
                                            new TextMessageBuilder($message));
      
                                            
      if ($jumlahVote == $jumlahPemain) 
      {
        $gamePlaying = $this->undercovergame_m->getPlayingGame($userGroupId);
        
        $civilianNumber = 0; //ambil pemain aktif saja_______________
        $undercoverNumber = 0;//ambil pemain aktif saja________________
        
        foreach ($pemain as $player) {
          if ($player->playing) {
            if($player->role == 'civilian'){
              $civilianNumber++;
            }else{
              $undercoverNumber++;
            }
            $this->undercovergame_m->votedStatus($player->user_id, $player->room_id, 'false');
          }
        }
        //DEBUG
        // echo PHP_EOL.$civilianNumber.$undercoverNumber.' game playing status '.$gamePlaying;

        if($gamePlaying && ($undercoverNumber < $civilianNumber) && ($undercoverNumber != 0))
        {
          $idVotedMax = null;
          $displayNameVotedMax = null;
          $votedMax = 0;
          // kirim hasil vote untuk pemain dengan nilai vote terbanyak
          // code...___________________
          foreach ($pemain as $player) {
            if ($votedMax < $player->voted_number) {
              $idVotedMax = $player->user_id;
              $displayNameVotedMax = $player->display_name;
              $votedMax = $player->voted_number;
            }
          }
          // non aktifkan nilai playing dari player
          // code...___________________
          $this->undercovergame_m->setPlayerPlaying($userGroupId, $idVotedMax, 'false');

          //announcement
          $multiMessageBuilder = new MultiMessageBuilder();
          $message = $displayNameVotedMax.' dikeluarkan dari permainan dengan total vote .'.$votedMax;
          $message2 = 'Silahkan lanjutkan permainan. Untuk melakukan vote, periksa personal chat pada bot';
          
          //DEBUG
          // echo $message;

          $multiMessageBuilder->add( new TextMessageBuilder($message));
          $multiMessageBuilder->add( new TextMessageBuilder($message2));
          
          $response = $this->bot->pushMessage($userGroupId, $multiMessageBuilder);

          ///// kirim balik ulang vote untuk player yang masih playing 
          // $pemain = $this->undercovergame_m->getPlayer($userGroupId)->result(); //ubah menjadi get active user____________
          $judul = 'Vote eksekusi';
          $kalimat = 'Silahkan pilih pemain yang akan dieksekusi';
          $playerButtons = [];
          $i = 0;
          foreach ($pemain as $player) {
            if ($player->playing == true) {
              $playerButtons[$i] = new PostbackTemplateActionBuilder($player->display_name, $player->user_id);
              $i++;
            }
          }
          // buat pesan
          $imageUrl = 'https://cdn.dribbble.com/users/881160/screenshots/2152292/undercover-icon.png';
          $buttonTemplateBuilder = new ButtonTemplateBuilder(
              $judl,
              $kalimat,
              $imageUrl,
              $playerButtons
          );
          $templateMessage = new TemplateMessageBuilder('Cek pesan pada smartphone', $buttonTemplateBuilder);
          
          // kirim pesan ke semua pemain
          foreach ($pemain as $player) {
            if ($player->playing == true) {
              # code...
              $response = $this->bot->pushMessage($player->user_id, $templateMessage);
            }
            //DEBUG
            // echo PHP_EOL.$player->display_name.' telah dikirimi vote baru';
          }


        }
        else ///permainan berakhir
        {
          $pemenang = $undercoverNumber == 0 ? 'civilian':'undercover';

          $message = strtoupper($pemenang).' memenangkan permainan'.PHP_EOL;

          // $pemain = $this->undercovergame_m->getPlayer($userGroupId)->result();
          
          $message .= PHP_EOL.'Selamat kepada:'.PHP_EOL;

          foreach ($pemain as $player) {
            if ($player->role == $pemenang) {
              $message = $message.PHP_EOL.$player->display_name;
            }
            //DEBUG
            // echo PHP_EOL.$player->display_name.' ada di list pemain sebagai '.$player->role;
          }

          //echo PHP_EOL.$message;

          $multiMessageBuilder = new MultiMessageBuilder();
          $message2 = '[PERMAINAN BERAKHIR]'.PHP_EOL.PHP_EOL;
          $message2 .= 'Silahkan buat permainan baru untuk bermain kembali';
          $multiMessageBuilder->add( new TextMessageBuilder($message));
          $multiMessageBuilder->add( new TextMessageBuilder($message2));
          
          $response = $this->bot->pushMessage($userGroupId, $multiMessageBuilder);

          // $response = $this->bot->pushMessage($userGroupId, 
          //                                       new TextMessageBuilder($message));
          
          // JANGAN LUPA NYALAIN
          // $this->undercovergame_m->resetPlayer($userGroupId);
          // $this->undercovergame_m->deleteGame($userGroupId);

        }

      }
      
    }

  // }

}