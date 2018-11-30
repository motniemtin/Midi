<?php
namespace Motniemtin\Midi;
use Exception;
class MidiToMp3{
  function init(){
    if(!function_exists('exec')) {
        throw new Exception("Exec can't run!");
    } 
    exec("whereis fluidsynth", $out, $return);
    $found=false;
    foreach($out as $line){
      if(substr_count($line, "/fluidsynth")){
        $found=true;break;
      }
    }
    if(!$found){
      throw new Exception("Please install fluidsynth!");
    }
  }
  public function toMp3($soundfont,$gain, $midi_path, $mp3_path, $tmp_folder){
    $this->init();
    if(!file_exists($soundfont)){
      throw new Exception("No soundfont file found!");
    }
    if(!file_exists($midi_path)){
      throw new Exception("No midi file found!");
    }
    if(!is_dir($tmp_folder)){
      throw new Exception("Temp folder is not exists!");
    }
    echo "start $midi_path convert to wav..\n";
    $wav=$tmp_folder."/".time().".wav";
    if(file_exists($wav)){
      if(file_exists($wav))unlink($wav);
    }
    $cmd="fluidsynth -F $wav $soundfont -g $gain ".$midi_path;
    exec($cmd);
    if(filesize($wav)<1000){
      if(file_exists($wav))unlink($wav);
      throw new Exception("Error when convert WAV, file too small");
    }
    //exit("wav ready: http://youtube.wikieda.org/Piano/manual/out/$id.wav");
    $cmd=getcwd()."/scripts/bin/ffmpeg -i $wav -ab 320k ".$mp3_path;
    exec($cmd);
    if(filesize($mp3_path)<1000){
      unlink($mp3_path);
      throw new Exception("Error when convert WAV, file too small");
    }
    if(file_exists($wav))unlink($wav);
    echo "convert to mp3 successful!\n";
  }
}
