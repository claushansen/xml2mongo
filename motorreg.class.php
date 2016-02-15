<?php

include_once('XML2Mongo.class.php');

class Motorreg extends XML2Mongo{
	
	
	
	//function for preprocessing of dataobject before saving it to database
	protected function preprocess($obj){
		
		$ret = new stdClass();
		
		//debug info
		if($this->debug){
			if($this->debug_show_objects){
				echo 'preprocessing SimpleXMLobject '.$this->processcounter.'<br>';
				echo 'Object before processing:<br>';
				var_dump($obj);
			}
		}
		
		$obj = json_decode(json_encode($obj));
		
		//collecting maerker,Models and variants
		$ret->_id = $obj->KoeretoejIdent;
		$ret->Licensplate = $obj->RegistreringNummerNummer;
		$ret->Regstatus = $obj->KoeretoejOplysningGrundStruktur->KoeretoejOplysningStatus;
		$ret->ModelYear = (int)date('Y', strtotime($obj->KoeretoejOplysningGrundStruktur->KoeretoejOplysningFoersteRegistreringDato));
		//(int)$obj->KoeretoejOplysningGrundStruktur->KoeretoejOplysningModelAar;
		$ret->VehicleType = $obj->KoeretoejArtNavn;
		$ret->BrandId = $obj->KoeretoejOplysningGrundStruktur->KoeretoejBetegnelseStruktur->KoeretoejMaerkeTypeNummer;
		$ret->Brand = $obj->KoeretoejOplysningGrundStruktur->KoeretoejBetegnelseStruktur->KoeretoejMaerkeTypeNavn;
		$ret->ModelId = $obj->KoeretoejOplysningGrundStruktur->KoeretoejBetegnelseStruktur->Model->KoeretoejModelTypeNummer;
		$ret->Model = $obj->KoeretoejOplysningGrundStruktur->KoeretoejBetegnelseStruktur->Model->KoeretoejModelTypeNavn;
		if(isset($obj->KoeretoejOplysningGrundStruktur->KoeretoejBetegnelseStruktur->Variant->KoeretoejVariantTypeNummer)){
		$ret->VariantId = $obj->KoeretoejOplysningGrundStruktur->KoeretoejBetegnelseStruktur->Variant->KoeretoejVariantTypeNummer;
		$ret->Variant = $obj->KoeretoejOplysningGrundStruktur->KoeretoejBetegnelseStruktur->Variant->KoeretoejVariantTypeNavn;
		}
		if(isset($obj->KoeretoejOplysningGrundStruktur->KoeretoejMotorStruktur->KoeretoejMotorSlagVolumen)){
			$motorvol = (float)$obj->KoeretoejOplysningGrundStruktur->KoeretoejMotorStruktur->KoeretoejMotorSlagVolumen / 1000;
			$motorvol = number_format($motorvol,1);
			$ret->Engine = (float)$motorvol;
		}
		
	
		//debug info
		if($this->debug){
			if($this->debug_show_objects){
				echo 'Object after processing:<br>';
				var_dump($ret);
			}		
		}
		
		// return the processed object
		return $ret;
			
	}
	
		public function init(){
		//debug info
		if($this->debug){
		$this->debug_start_time = date("Y-m-d H:i:s");	
		echo 'Starttime: '. $this->debug_start_time.'<br>';
		}
		
		// connecting to localhost mongo if no url is set
		$mongoinstance = '';
		if($this->mongourl){
			$mongoinstance = $this->mongourl;
		}
		$this->mongocon = new MongoClient($mongoinstance);
		
		//debug info
		if($this->debug){
			echo 'MongoDB connection:<br>';
			var_dump($this->mongocon->getConnections());	
		}
		
		//TODO - Make function to drop existing databases
		
		if($this->source_xml_path  &&  is_file($this->source_xml_path)){
			$this->reader->open($this->source_xml_path);
			//debug info
			if($this->debug){
				echo 'XMLReader has loaded: '.$this->source_xml_path.'<br>';	
			}
			}else{
			die("Can't read source xml path! Exiting..");	
			}
			
		//debug info	
		if($this->debug){
			echo 'XMLReader starts reading<br>';	
			}
				
		while($this->reader->read())
		  {
			  if($this->reader->nodeType == XMLReader::END_ELEMENT  && $this->reader->name == 'ns:StatistikSamling') {
				  //debug info
				  if($this->debug){
					echo 'XMLReader has reached the end of the xmlfile<br>';	
					}
				  break;
			  	}
			  
			  if($this->reader->nodeType == XMLReader::ELEMENT){
					//debug info
					if($this->debug){
						//echo 'processed node<br>';
						//echo $this->reader->name.'<br>';
						}  
					}
				
			  if($this->reader->nodeType == XMLReader::ELEMENT && $this->reader->name == 'ns:Statistik')
			  {
				  //create new domdocument
				  $doc = new DOMDocument('1.0', 'UTF-8');
				  //extract nodeelement
				  $xmldump = $this->reader->expand();
				  //import node in domdocument and create an SimpleXML Object from it
				  $xml = simplexml_import_dom($doc->importNode($xmldump,true));
				  //fetch namespaceses
				  $namespaces = $xml->getNamespaces(true);
				  //Create namespace object we can work with
				  $ns = $xml->children($namespaces["ns"]);
				  
				  //testing if we have collect filters and setting a flag that we wants it to be processed
				  $we_want_it = $this->do_we_want_this($ns);
				
				  //send the object to pre processing
				  if($we_want_it){
				  	$processed = $this->preprocess($ns);
				  }
				  
				  // Save the vehicle object to database
				  if($this->collect_vehicles && $we_want_it){				  
				  	$this->save($processed,'motorregister','vehicles');
				  }
				  //Unsets objects to free memory
				  unset($ns);
				  unset($processed);
		
				  
				  $this->processcounter ++;
				  
			  }
			  if($this->limit && $this->limit == $this->processcounter){
				  //debug info
				  if($this->debug){
					  echo 'XMLReader has reached the limit: '.$this->limit.'<br>';	
					  }
				  break;  
				}
			  }
		  //allmost done, closing connection and reader
		  $this->mongocon->close();
		  $this->reader->close();
		  
		  //calculating processtime for debug
		  $this->debug_end_time = date("Y-m-d H:i:s");
		  $date_a = new DateTime($this->debug_start_time);
		  $date_b = new DateTime($this->debug_end_time);
		  $interval = date_diff($date_a,$date_b);
		  
		  
		  //debug info
		  if($this->debug){
			echo 'Connection closed<br>';
			echo 'DONE! processed '.$this->processcounter.'<br>';
			echo 'Finished time: '.$this->debug_end_time.'<br>';
			echo 'The process time was '.$interval->format('%H:%I:%S');  
		  }
	}
	
	
}
