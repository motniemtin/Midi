<?php

namespace Motniemtin\Midi;
use Exception;
use Motniemtin\Midi\Midi;
use \Done\Subtitles\Subtitles;
class MidiTools{
  var $xml,$notes,$events,$duration,$measure;
  var $json;
  var $song=array();
  var $title, $artist;
  var $lyrics=array();
  var $lines=array();
  public function loadFile($title, $artist, $midi_path, $midi_json_path=""){
    
    $this->title=$title;
    $this->artist=$artist;
    $midi=new Midi();
    $midi->importMid($midi_path); 
    $this->xml=simplexml_load_string($midi->getXml());
    if($midi_json_path!="")
      $this->json=json_decode(file_get_contents($midi_json_path),1);
    $this->createTime();
  }
  public function toLyric($lyric_path){
    $subtitles = new Subtitles();        
    foreach($this->song['tracks'] as $track){           
      if(!isset($track['data']))continue;
      foreach($track['data'] as $key => $note){
        if(count($note['lyrics'])!=0){
          $_lyric="";
          foreach($note['lyrics'] as $lyric){
            $_lyric.=($lyric)."\n";
          }
          $_lyric=trim($_lyric);
          if($_lyric!=""){
            //echo $_lyric."\n";
            $subtitles->add($note['start'], $note['stop'], $_lyric);     
            $this->lyrics[(String)$note['start']]=array('start' => $note['start'], 'stop' => $note['stop'], 'lyric' => $_lyric);
          }          
        }   
      }
    }
    $subtitles->save($lyric_path);
    ksort($this->lyrics);
    $line=array();
    $lines=array();
    $line_string="";
    while ($_lyric = current($this->lyrics) )
    {
        $need_add=false;
        $line[]=$_lyric;
        if(substr_count($_lyric['lyric'], ".")){
          $need_add=true;
        }
        if(substr_count($_lyric['lyric'], ",")){
          $need_add=true;
        }
        $next_lyric = next($this->lyrics);
        if (false !== $next_lyric){
          if(($next_lyric['start']-$_lyric['stop'])>3){
            $need_add=true;
          }
        }else{
          //end element
          $need_add=true;
        }
        if($need_add){
          if(count($line)>0){
            $lines[]=$line;
            $line=array();
          }            
        }
    }
    foreach($lines as $line){
      //print_r($line);exit();
      $data=array();
      $first=$line[0];
      $end=end($line);
      $data['start']=$first['start'];
      $data['stop']=$end['stop'];
      $data['lines']=array();
      foreach($line as $words){
        //print_r($line);exit();
        $line_lyrics=explode("\n",$words['lyric']);
        for($i=0;$i<count($line_lyrics);$i++){
          $word=$line_lyrics[$i];
          if(trim($word)!=""){
            if(!isset($data['lines'][$i])){
              $data['lines'][$i]=array();
              $data['lines'][$i]['string']="";
              $data['lines'][$i]['words']=array();
            }
            $data['lines'][$i]['string'].=" ".$word;
            $data['lines'][$i]['words'][(string)$words['start']]=array('text' => $word, 'start' => $words['start'], 'stop' => $words['stop']);
            $data['lines'][$i]['string']=trim($data['lines'][$i]['string']);
          }
        }
      }   
      foreach($data['lines'] as $temp){
        echo $data['start'].' => '.$temp['string']."\n";
      }
    }
  }
  public function toTime($json_path){
//     $array=array();
//     $array['notes']=$this->notes;
//     $array['events']=$this->events;
//     $array['duration']=$this->duration;
    file_put_contents($json_path, json_encode($this->song));
  }  
  private function createTime(){
    //print_r($this->xml);
    $TicksPerBeat;
    if(isset($this->xml->TicksPerBeat)){
      $TicksPerBeat=$this->xml->TicksPerBeat;
    }
    $maxTime=0;
    //print_r($this->xml);exit();
    //print_r($this->xml->Track);exit();
    $SetTempoFirst=null;
    
    $tempo_Array=array();
    $last_tempo_absolute=0;
    $last_time=0;
    $last_tempo=0;
    $instrument;
    foreach($this->xml->Track as $track){
      foreach($track->Event as $event){
        if(isset($event->SetTempo)){
          if($this->measure==null){
            $this->measure=4*((int)$event->SetTempo['Value'])/1000000;
          }          
          if(!isset($tempo_Array[0])&&((int)$event->Absolute!=0)){
            $tempo_Array[0]=array(
              'absolute'=> 0, 
              'tempo' => 500000, 
              'next' => null,
              'time' => 0
            );   
            $last_tempo_absolute=0;
            $last_tempo=500000;
            $last_time=0;
          }
          $last_time=((int)$event->Absolute-$last_tempo_absolute)*($last_tempo)/$TicksPerBeat/1000000+$last_time;
          $last_tempo_absolute=(int)$event->Absolute;
          $last_tempo=(int)$event->SetTempo['Value'];
          $tempo_Array[(int)$event->Absolute]=array(
            'absolute'=> (int)$event->Absolute, 
            'tempo' => (int)$event->SetTempo['Value'], 
            'next' => null,
            'time' => $last_time
          );
        }
      }
    }
    $instruments=array();
    $lastInstrument=0;
    foreach($this->xml->Track as $track){
      $trackNumber=(int)$track['Number'];
      //echo "track $trackNumber \n";
      foreach($track->Event as $event){
        if(isset($event->ProgramChange)){
          $channel=$event->ProgramChange['Channel'];
          $number=(int)$event->ProgramChange['Number'];
          if($channel==10){
            $number=128;
          }
          $number+=1;    
          $lastInstrument=$number;
          $instruments[$trackNumber]=$lastInstrument;
          if(isset($this->json[(String)($trackNumber+1)])){
            //here...
            //$this->json[$trackNumber]['instrument']=$lastInstrument;
            if(!isset($this->song[($trackNumber+1)])){
              $this->song[($trackNumber+1)]=array();
            }
            $this->song[($trackNumber+1)]['instrument']=$lastInstrument;
          }
          continue;
        }
      }
      if(!isset($instruments[$trackNumber]))$instruments[$trackNumber]=$lastInstrument;
    }
    //print_r($instruments);exit();
    $lastItem=null;
    $tempo_Array_New=array();
    $absolute=0;
    foreach($tempo_Array as $tempo_ar){
      if($lastItem!=null){   
        $lastItem['next']=$tempo_ar['absolute'];        
        $tempo_Array_New[$lastItem['absolute']]=$lastItem;
      }
      $lastItem=$tempo_ar;
    }
    
    $lastItem=end($tempo_Array);
    $tempo_Array_New[$lastItem['absolute']]=$lastItem;
    $tempo_Array=$tempo_Array_New;
    //print_r($tempo_Array);exit();
    $piano_track_count=0;
    foreach($this->xml->Track as $track){
      $tempoItem=$tempo_Array[0];
      //echo "current tempo: ".$tempoItem['tempo']."\n";
      $trackNumber=(int)$track['Number'];
      
      if($instruments[$trackNumber]>8)continue;
      foreach($track->Event as $event){      
        if(isset($event->NoteOn) && isset($event->Absolute)){                    
          if(!isset($event->NoteOn['Note']) || !isset($event->NoteOn['Velocity']))continue;
          //echo "current absolute: ".(int)$event->Absolute."\n";
          if(isset($tempoItem['next'])){
            if((int)$event->Absolute>=$tempoItem['next']){
              $tempoItem=$tempo_Array[$tempoItem['next']];
              //echo "current tempo: ".$tempoItem['tempo']."\n";
            }
          }
          
          $note=(int)$event->NoteOn['Note'];
          $velocity=(int)$event->NoteOn['Velocity'];
          if(!isset($this->notes[$note])){
            $this->notes[$note]=array();
          }
          if($velocity==0){
            if(count($this->notes[$note])>0){   
              foreach (array_reverse($this->notes[$note]) as $_note ) {
                if($_note['track']==$trackNumber){
                  $lastTempoItem=$_note['tempo'];
                  break;
                }
              }
            }else{
              $lastTempoItem=$tempoItem;
            }            
            $noteTime=$lastTempoItem['time']+(abs((int)$event->Absolute-$lastTempoItem['absolute'])*$lastTempoItem['tempo']/$TicksPerBeat/1000000);
            //echo "note time: $noteTime\n";
            if($maxTime<$noteTime)$maxTime=$noteTime;
            if(isset($this->json[(String)($trackNumber+1)][(string)$event->Absolute])){
              $this->json[(String)($trackNumber+1)][(string)$event->Absolute]['type']='stop';
              $this->json[(String)($trackNumber+1)][(string)$event->Absolute]['time']=$noteTime;
            }
          }else{
            $noteTime=$tempoItem['time']+(abs((int)$event->Absolute-$tempoItem['absolute'])*$tempoItem['tempo']/$TicksPerBeat/1000000);
            if(isset($this->json[(String)($trackNumber+1)][(string)$event->Absolute])){
              $this->json[(String)($trackNumber+1)][(string)$event->Absolute]['type']='start';
              $this->json[(String)($trackNumber+1)][(string)$event->Absolute]['time']=$noteTime;
            }
              
            //echo "note time: $noteTime\n";
            if($maxTime<$noteTime)$maxTime=$noteTime;
            $this->notes[$note][]=array('type' => 'start', 'time' => $noteTime, 'track'=> $trackNumber, 'tempo' => $tempoItem);
          }            
        }
      }
      $this->duration=$maxTime;
      $piano_track_count+=1;     
    }
    if($piano_track_count==0){
      throw new Exception("No Piano Sheet found!");
    }
    //echo "xml duration: ".$this->duration."\n";
    //exit();
    // //echo $this->duration."--duration\n";
    //exit();
    $this->notes=(Object)$this->notes;    
    $note_pressed=array();
    $temp_array=array();
    foreach($this->notes as $note_key => $notes){
      foreach($notes as $note){        
        $note['name']=$note_key;
        $temp_array[(string)$note['time']][]=$note;
      }
    }
    ksort($temp_array);
    //print_r($this->notes);exit();
    //print_r($temp_array);exit();exit();
    $key_pressed=array();
    foreach($temp_array as $time => $notes){
      foreach($notes as $note){
        $note_number=intval($note['name']);
        if($note_number<21 || $note_number>108)continue;
        if($note['type']=='start'){
          $key_pressed[intval($note['name'])]=array('time' => $note['time'], 'track' => $note['track']);
        }
        if($note['type']=='stop'){
          if(isset($key_pressed[intval($note['name'])])){
            unset($key_pressed[intval($note['name'])]);
          }
        }
      }
      if(count($key_pressed)!=0)
      $this->events[$time]=array('keys'=>$key_pressed, 'next' => null);
    }    
    $last_id=null;
    $last_event=null;
    foreach($this->events as $time => $event){
      if($last_event!=null){  
        $last_event['next']=$time;
        $this->events[$last_id]=$last_event;
      }
      $last_event=$event;
      $last_id=$time;
    }
    //echo "event count: ".count($this->events)." \n";
    //print_r($this->events);exit();
    $noteEvents=array();
    foreach($this->json as $key => $track){
      $_track=array();
      //print_r($track);exit();
      if(is_array($track))ksort($track);
      foreach($track as $key => $notes){
        if($notes['type']=='start'){
          foreach($notes['notes'] as $key => $note){
            $note['start']=$notes['time'];
            $noteEvents[(string)$note['pitch']]=$note;
          }
        }
        if($notes['type']=='stop'){
          foreach($notes['notes'] as $key => $note){
            if(isset($noteEvents[(string)$note['pitch']])){
              $start_note=$noteEvents[(string)$note['pitch']];
              $start_note['stop']=$notes['time'];
              $_track[(String)$start_note['start']]=$start_note;
            }
          }
        }
      }
      if(!isset($this->song[$key])){
        $this->song[$key]=array();
      }      
      $this->song[$key]['data']=$_track;
    }    
    $full_song=array();
    $full_song['duration']=$this->duration;
    $full_song['tracks']=$this->song;
    $full_song['title']=$this->title;
    $full_song['artist']=$this->artist;
    $this->song=$full_song;
  }
}