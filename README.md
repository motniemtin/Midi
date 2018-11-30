Class for Midi file,
Help to convert midi to txt format, caculator midi duration, convert type 0 <--> type 1

<h1>Install</h1>
<div>composer require motniemtin/midiclass</div>
  
<h1>Use</h1>
<div>
  
<code>
  use MidiClass\Midi;
</code> 

  //Instanciates a midi
    
<code>  
  $midi = new Midi();
</code>  


  //open midi file
  
<code>  
    $midi->importMid($file);
</code>


  //get Tracks Count
  
<code>  
  $midi->getTrackCount();
</code>  


  //get tempo
  
<code>  
  $midi->getTempo();  
</code>  


  //get Midi Text format
  
<code>  
  $midi->getTxt();
</code>  


  //get Midi xml format
  
<code> 
  $midi->getXml();
</code>  

</div>
  


  
  
