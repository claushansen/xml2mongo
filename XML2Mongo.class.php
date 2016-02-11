<?php
/**
* XML2Mongo is a class for processing xmldatadump from Motorregister in denmark
* with all vehicles registered in denmark
*
*
*
*/

class XML2Mongo{
	
	private $source_xml_path = NULL;
	
	private $mongourl = NULL;
	
	private $mongocon = NULL;
	
	private $mongodb = NULL;
	
	private $limit = NULL;
	
	private $debug = false;
	
	private $debug_start_time = NULL;
	
	private $debug_end_time = NULL;
	
	private $debug_show_objects = false;
	
	private $reader = NULL;
	
	private $collect_vehicles = true;
	
	private $collect_filter = NULL;
	
	private $collect_unique_only = true;
	
	private $collect_registered_only = true;
	
	private $collect_extras = true;
	
	private $maerker = array();
	
	private $modeller = array();
	
	private $varianter = array();
	
	private $processcounter = 0;
	
	public function __construct(){
		$this->reader = new XMLReader();
	}
	
	
	public function set_xml_source_path($path){
		
		$this->source_xml_path = $path;
		if($this->debug){
		echo 'xml_source_path is set to: '.$path.'<br>';	
		}
		return $this;		
		
	}
	
	public function set_mongo_connection($con){
		
		$this->mongocon = $con;		
		return $this;
	}
	
	public function set_mongo_db($db){
		
		$this->mongodb = $db;
		if($this->debug){
		echo 'Mongo database is set to: '.$db.'<br>';	
		}
		return $this;		
		
	}
	
	public function set_limit($limit){
		
		$this->limit = $limit;
		if($this->debug){
		echo 'Limit is set to: '.$limit.'<br>';	
		}
		return $this;		
		
	}
	
	public function set_collect_vehicles($bool){
		
		$this->collect_vehicles = $bool;
		if($this->debug){
		echo 'Collect vehicles: '.$bool.'<br>';	
		}
		return $this;		
		
	}
	
	/**
	* Function $set_collect_filter
	*
	* Adds filter to collect_filter array().
	*
	* If any filters present only those types of vehicles will be collected.
	* Possible values:'Campingvogn', 'Lastbil', 'Lille knallert', 
	* 'Motorcykel', 'Motorredskab', 'Påhængsredskab', 'Påhængsvogn',
	* 'Personbil', 'Sættevogn', 'Stor knallert', 'Stor personbil',
	* 'Traktor', 'Traktorpåhængsvogn', 'Varebil'  
	*
	* @param String $collect_this 
	* @return Object $this 
	*/
	public function set_collect_filter($collect_this){
		if(!is_array($this->collect_filter))$this->collect_filter = array();
		
		$this->collect_filter[] = $collect_this;
		if($this->debug){
			echo 'Collect_filter set to include: '.$collect_this.'<br>';	
		}
		return $this;		
		
	}
	
	public function set_collect_extras($bool){
		
		$this->collect_extras = $bool;
		if($this->debug){
		echo 'Collect extras: '.$bool.'<br>';	
		}
		return $this;		
		
	}
	
	/**
	* Function set_collect_unique_only
	* 
	* Sets $collect_unique_only flag, defaults to true.
	* 
	* If set to true we create an _id of the vehicles KoeretojeIdent before inserting to database  
	* causing Mongo to overwrite if this _id allready exists.  
	* If set to false, Mongo will create an objectid object as primary key and not do any overide of instances  
	* of the same vehicle  
	*
	* @param Boolean $bool
	* @return Object $this
	*/
	public function set_collect_unique_only($bool){
		
		$this->collect_unique_only = $bool;
		if($this->debug){
		echo 'Collect unique vehicles only: '.$bool.'<br>';	
		}
		return $this;		
		
	}
	
	/**
	* Function set_collect_registered_only
	* 
	* Sets $collect_registered_only flag, defaults to true.
	* 
	* If set to false we collects vehicles that have no licensplate to  
	*
	* @param Boolean $bool
	* @return Object $this
	*/
	public function set_collect_registered_only($bool){
		
		$this->collect_registered_only = $bool;
		if($this->debug){
		echo 'Collect registered vehicles only: '.$bool.'<br>';	
		}
		return $this;		
		
	}
	
	public function set_debug($bool){
		
		$this->debug = $bool;
		return $this;		
		
	}
	
	public function set_debug_show_objects($bool){
		
		$this->debug_show_objects = $bool;
		if($this->debug){
		echo 'debug_show_objects: '.$bool.'<br>';	
		}
		return $this;		
		
	}
	
	protected function recursive_array_search($needle,$haystack) {
		foreach($haystack as $key=>$value) {
			$current_key=$key;
			if($needle===$value OR (is_array($value) && $this->recursive_array_search($needle,$value) !== false)) {
				return $current_key;
			}
		}
		return false;
	}
	
	//function for preprocessing of dataobject before saving it to database
	protected function preprocess($obj){
		//debug info
		if($this->debug){
			if($this->debug_show_objects){
				echo 'preprocessing SimpleXMLobject '.$this->processcounter.'<br>';
				echo 'Object before processing:<br>';
				var_dump($obj);
			}
		}
		
			
		//var_dump($obj->KoeretoejSupplerendeKarrosseriSamlingStruktur->KoeretoejSupplerendeKarrosseriSamling->KoeretoejSupplerendeKarrosseriTypeStruktur);
		//Removing elements that we don't need
		unset($obj->KoeretoejOplysningGrundStruktur->KoeretoejFarveStruktur);
		unset($obj->KoeretoejOplysningGrundStruktur->KoeretoejUdstyrSamlingStruktur);
		unset($obj->KoeretoejOplysningGrundStruktur->KoeretoejNormStruktur);
		unset($obj->TilladelseSamling);
		//unset($obj->KoeretoejSupplerendeKarrosseriSamlingStruktur->KoeretoejSupplerendeKarrosseriSamling->KoeretoejSupplerendeKarrosseriTypeStruktur[0]);
		unset($obj->EjerBrugerSamling);
				
		// converting object to stdClass
		// If you need simpleXml methods do it before this line
		$obj = json_decode(json_encode($obj));
		
		//Create _id from KoeretoejIdent
		if($this->collect_unique_only){
			$obj->_id = $obj->KoeretoejIdent;
		}
		
		if ($this->collect_extras){
		
		//collecting maerker,Models and variants
		$koeretoejart = $obj->KoeretoejArtNavn;
		$maerkeid = $obj->KoeretoejOplysningGrundStruktur->KoeretoejBetegnelseStruktur->KoeretoejMaerkeTypeNummer;
		$maerkenavn = $obj->KoeretoejOplysningGrundStruktur->KoeretoejBetegnelseStruktur->KoeretoejMaerkeTypeNavn;
		$modelid = $obj->KoeretoejOplysningGrundStruktur->KoeretoejBetegnelseStruktur->Model->KoeretoejModelTypeNummer;
		$modelnavn = $obj->KoeretoejOplysningGrundStruktur->KoeretoejBetegnelseStruktur->Model->KoeretoejModelTypeNavn;
		if(isset($obj->KoeretoejOplysningGrundStruktur->KoeretoejBetegnelseStruktur->Variant->KoeretoejVariantTypeNummer)){
		$variantid = $obj->KoeretoejOplysningGrundStruktur->KoeretoejBetegnelseStruktur->Variant->KoeretoejVariantTypeNummer;
		$variantnavn = $obj->KoeretoejOplysningGrundStruktur->KoeretoejBetegnelseStruktur->Variant->KoeretoejVariantTypeNavn;
		}
		//Storing maerke in $this->maerker
		
/*		if(!isset($this->maerker[$koeretoejart]))$this->maerker[$koeretoejart] = array();
		$hasMaerke = $this->recursive_array_search($maerkeid,$this->maerker[$koeretoejart]);
		// if not, we save it for later
		if($hasMaerke === false){
			
			$this->maerker[$koeretoejart][] = array('_id' => $maerkeid, 'maerke' => $maerkenavn );
			
		}*/
		if(isset($obj->KoeretoejOplysningGrundStruktur->KoeretoejBetegnelseStruktur->KoeretoejMaerkeTypeNummer)){
		$maerkearr = array('_id' => $maerkeid, 'maerke' => $maerkenavn );
		$this->save((object)$maerkearr,$koeretoejart,'maerke');
		}
		//Storing model in $this->modeller
		
		/*if(!isset($this->modeller[$koeretoejart]))$this->modeller[$koeretoejart] = array();
		$hasModel = $this->recursive_array_search($modelid,$this->modeller[$koeretoejart]);
		// if not, we save it for later
		if($hasModel === false){
			$this->modeller[$koeretoejart][] = array('_id' => $modelid, 'model' => $modelnavn, 'maerke_id' => $maerkeid  );
		}*/
		if(isset($obj->KoeretoejOplysningGrundStruktur->KoeretoejBetegnelseStruktur->Model->KoeretoejModelTypeNummer)){
		$modelarr = array('_id' => $modelid, 'model' => $modelnavn, 'maerke_id' => $maerkeid  );
		$this->save((object)$modelarr,$koeretoejart,'model');
		}
		//Storing variants in $this->varianter
		/*if(isset($obj->KoeretoejOplysningGrundStruktur->KoeretoejBetegnelseStruktur->Variant->KoeretoejVariantTypeNummer)){
		if(!isset($this->varianter[$koeretoejart]))$this->varianter[$koeretoejart] = array();
		$hasVariant = $this->recursive_array_search($variantid,$this->varianter[$koeretoejart]);
		// if not, we save it for later
		if($hasVariant === false){
			$this->varianter[$koeretoejart][] = array('_id' => $variantid, 'variant' => $variantnavn, 'model_id' => $modelid );
		}
		}*/
		if(isset($obj->KoeretoejOplysningGrundStruktur->KoeretoejBetegnelseStruktur->Variant->KoeretoejVariantTypeNummer)){
		$variantarr = array('_id' => $variantid, 'variant' => $variantnavn, 'model_id' => $modelid );
			$this->save((object)$variantarr,$koeretoejart,'variant');
		
		}
		
		}//end if $this->collect_extras
		
		//repacking objects(needs to do this in correct order or it will fail)
		
		//KoeretoejAnvendelseStruktur
		$obj = $this->repack($obj,$obj->KoeretoejAnvendelseStruktur);
		unset($obj->KoeretoejAnvendelseStruktur);
		
		//KoeretoejBetegnelseStruktur->Model
		$obj = $this->repack($obj,$obj->KoeretoejOplysningGrundStruktur->KoeretoejBetegnelseStruktur->Model);
		unset($obj->KoeretoejOplysningGrundStruktur->KoeretoejBetegnelseStruktur->Model);
		
		//KoeretoejBetegnelseStruktur->Variant
		$obj = $this->repack($obj,$obj->KoeretoejOplysningGrundStruktur->KoeretoejBetegnelseStruktur->Variant);
		unset($obj->KoeretoejOplysningGrundStruktur->KoeretoejBetegnelseStruktur->Variant);
		
		//KoeretoejBetegnelseStruktur->Type
		$obj = $this->repack($obj,$obj->KoeretoejOplysningGrundStruktur->KoeretoejBetegnelseStruktur->Type);
		unset($obj->KoeretoejOplysningGrundStruktur->KoeretoejBetegnelseStruktur->Type);
		
		//KoeretoejBetegnelseStruktur
		$obj = $this->repack($obj,$obj->KoeretoejOplysningGrundStruktur->KoeretoejBetegnelseStruktur);
		unset($obj->KoeretoejOplysningGrundStruktur->KoeretoejBetegnelseStruktur);
		
		//KarrosseriTypeStruktur
		$obj = $this->repack($obj,$obj->KoeretoejOplysningGrundStruktur->KarrosseriTypeStruktur);
		unset($obj->KoeretoejOplysningGrundStruktur->KarrosseriTypeStruktur);
		
		//KoeretoejMiljoeOplysningStruktur
		$obj = $this->repack($obj,$obj->KoeretoejOplysningGrundStruktur->KoeretoejMiljoeOplysningStruktur);
		unset($obj->KoeretoejOplysningGrundStruktur->KoeretoejMiljoeOplysningStruktur);
		
		//KoeretoejMotorStruktur->DrivkraftTypeStruktur
		$obj = $this->repack($obj,$obj->KoeretoejOplysningGrundStruktur->KoeretoejMotorStruktur->DrivkraftTypeStruktur);
		unset($obj->KoeretoejOplysningGrundStruktur->KoeretoejMotorStruktur->DrivkraftTypeStruktur);
		
		//KoeretoejMotorStruktur
		$obj = $this->repack($obj,$obj->KoeretoejOplysningGrundStruktur->KoeretoejMotorStruktur);
		unset($obj->KoeretoejOplysningGrundStruktur->KoeretoejMotorStruktur);
		
		//KoeretoejOplysningGrundStruktur
		$obj = $this->repack($obj,$obj->KoeretoejOplysningGrundStruktur);
		unset($obj->KoeretoejOplysningGrundStruktur);
		
		
		
		//Typecasting values to correct type(i.e strings to int,float,bools and so on)
		
		//ints
		//if(isset($obj->KoeretoejIdent))$obj->KoeretoejIdent = (int)$obj->KoeretoejIdent;
		if(isset($obj->KoeretoejArtNummer))$obj->KoeretoejArtNummer = (int)$obj->KoeretoejArtNummer;
		if(isset($obj->AdressePostNummer))$obj->AdressePostNummer = (int)$obj->AdressePostNummer;
		//if(isset($obj->KoeretoejAnvendelseNummer))$obj->KoeretoejAnvendelseNummer = (int)$obj->KoeretoejAnvendelseNummer;
		//if(isset($obj->KoeretoejModelTypeNummer))$obj->KoeretoejModelTypeNummer = (int)$obj->KoeretoejModelTypeNummer;
		//if(isset($obj->KoeretoejVariantTypeNummer)) $obj->KoeretoejVariantTypeNummer = (int)$obj->KoeretoejVariantTypeNummer;
		//if(isset($obj->KoeretoejMaerkeTypeNummer))$obj->KoeretoejMaerkeTypeNummer =(int)$obj->KoeretoejMaerkeTypeNummer;
		//if(isset($obj->KoeretoejTypeTypeNummer))$obj->KoeretoejTypeTypeNummer = (int)$obj->KoeretoejTypeTypeNummer;
		//if(isset($obj->KarrosseriTypeNummer))$obj->KarrosseriTypeNummer = (int)$obj->KarrosseriTypeNummer;
		if(isset($obj->KoeretoejMiljoeOplysningRoegtaethedOmdrejningstal))$obj->KoeretoejMiljoeOplysningRoegtaethedOmdrejningstal = (int)$obj->KoeretoejMiljoeOplysningRoegtaethedOmdrejningstal;
		if(isset($obj->DrivkraftTypeNummer))$obj->DrivkraftTypeNummer = (int)$obj->DrivkraftTypeNummer;
		if(isset($obj->KoeretoejMotorCylinderAntal))$obj->KoeretoejMotorCylinderAntal = (int)$obj->KoeretoejMotorCylinderAntal;
		if(isset($obj->KoeretoejMotorStandStoejOmdrejningstal))$obj->KoeretoejMotorStandStoejOmdrejningstal = (int)$obj->KoeretoejMotorStandStoejOmdrejningstal;
		if(isset($obj->KoeretoejOplysningModelAar))$obj->KoeretoejOplysningModelAar = (int)$obj->KoeretoejOplysningModelAar;
		if(isset($obj->KoeretoejOplysningTotalVaegt))$obj->KoeretoejOplysningTotalVaegt = (int)$obj->KoeretoejOplysningTotalVaegt;
		if(isset($obj->KoeretoejOplysningKoereklarVaegtMinimum))$obj->KoeretoejOplysningKoereklarVaegtMinimum = (int)$obj->KoeretoejOplysningKoereklarVaegtMinimum;
		if(isset($obj->KoeretoejOplysningTekniskTotalVaegt))$obj->KoeretoejOplysningTekniskTotalVaegt = (int)$obj->KoeretoejOplysningTekniskTotalVaegt;
		if(isset($obj->KoeretoejOplysningEgenVaegt))$obj->KoeretoejOplysningEgenVaegt = (int)$obj->KoeretoejOplysningEgenVaegt;
		if(isset($obj->KoeretoejOplysningVogntogVaegt))$obj->KoeretoejOplysningVogntogVaegt = (int)$obj->KoeretoejOplysningVogntogVaegt;
		if(isset($obj->KoeretoejOplysningAkselAntal))$obj->KoeretoejOplysningAkselAntal = (int)$obj->KoeretoejOplysningAkselAntal;
		if(isset($obj->KoeretoejOplysningStoersteAkselTryk))$obj->KoeretoejOplysningStoersteAkselTryk = (int)$obj->KoeretoejOplysningStoersteAkselTryk;
		if(isset($obj->KoeretoejOplysningPassagerAntal))$obj->KoeretoejOplysningPassagerAntal = (int)$obj->KoeretoejOplysningPassagerAntal;
		if(isset($obj->KoeretoejOplysningSiddepladserMinimum))$obj->KoeretoejOplysningSiddepladserMinimum = (int)$obj->KoeretoejOplysningSiddepladserMinimum;
		if(isset($obj->KoeretoejOplysningTilkoblingsvaegtUdenBremser))$obj->KoeretoejOplysningTilkoblingsvaegtUdenBremser = (int)$obj->KoeretoejOplysningTilkoblingsvaegtUdenBremser;
		if(isset($obj->KoeretoejOplysningTilkoblingsvaegtMedBremser))$obj->KoeretoejOplysningTilkoblingsvaegtMedBremser = (int)$obj->KoeretoejOplysningTilkoblingsvaegtMedBremser;
		if(isset($obj->KoeretoejOplysningMaksimumHastighed))$obj->KoeretoejOplysningMaksimumHastighed = (int)$obj->KoeretoejOplysningMaksimumHastighed;
		if(isset($obj->KoeretoejOplysningTraekkendeAksler))$obj->KoeretoejOplysningTraekkendeAksler = (int)$obj->KoeretoejOplysningTraekkendeAksler;
		if(isset($obj->KoeretoejOplysningAkselAfstand))$obj->KoeretoejOplysningAkselAfstand = (int)$obj->KoeretoejOplysningAkselAfstand;
		if(isset($obj->KoeretoejOplysningSporviddenForrest))$obj->KoeretoejOplysningSporviddenForrest = (int)$obj->KoeretoejOplysningSporviddenForrest;
		if(isset($obj->KoeretoejOplysningSporviddenBagest))$obj->KoeretoejOplysningSporviddenBagest = (int)$obj->KoeretoejOplysningSporviddenBagest;
		if(isset($obj->KoeretoejOplysningAntalDoere))$obj->KoeretoejOplysningAntalDoere = (int)$obj->KoeretoejOplysningAntalDoere;
		if(isset($obj->KoeretoejOplysningSiddepladserMaksimum))$obj->KoeretoejOplysningSiddepladserMaksimum = (int)$obj->KoeretoejOplysningSiddepladserMaksimum;
		if(isset($obj->KoeretoejMotorKilometerstand))$obj->KoeretoejMotorKilometerstand = (int)$obj->KoeretoejMotorKilometerstand;
		if(isset($obj->KoeretoejOplysningKoereklarVaegtMaksimum))$obj->KoeretoejOplysningKoereklarVaegtMaksimum = (int)$obj->KoeretoejOplysningKoereklarVaegtMaksimum;
		
		
		//floats
		if(isset($obj->KoeretoejMiljoeOplysningCO2Udslip))$obj->KoeretoejMiljoeOplysningCO2Udslip = (float)$obj->KoeretoejMiljoeOplysningCO2Udslip;
		if(isset($obj->KoeretoejMiljoeOplysningEmissionCO))$obj->KoeretoejMiljoeOplysningEmissionCO = (float)$obj->KoeretoejMiljoeOplysningEmissionCO;
		if(isset($obj->KoeretoejMiljoeOplysningEmissionHCPlusNOX))$obj->KoeretoejMiljoeOplysningEmissionHCPlusNOX = (float)$obj->KoeretoejMiljoeOplysningEmissionHCPlusNOX;
		if(isset($obj->KoeretoejMiljoeOplysningEmissionNOX))$obj->KoeretoejMiljoeOplysningEmissionNOX = (float)$obj->KoeretoejMiljoeOplysningEmissionNOX;
		if(isset($obj->KoeretoejMiljoeOplysningPartikler))$obj->KoeretoejMiljoeOplysningPartikler = (float)$obj->KoeretoejMiljoeOplysningPartikler;
		if(isset($obj->KoeretoejMiljoeOplysningRoegtaethed))$obj->KoeretoejMiljoeOplysningRoegtaethed = (float)$obj->KoeretoejMiljoeOplysningRoegtaethed;
		if(isset($obj->KoeretoejMotorSlagVolumen))$obj->KoeretoejMotorSlagVolumen = (float)$obj->KoeretoejMotorSlagVolumen;
		if(isset($obj->KoeretoejMotorStoersteEffekt))$obj->KoeretoejMotorStoersteEffekt = (float)$obj->KoeretoejMotorStoersteEffekt;
		if(isset($obj->KoeretoejMotorKmPerLiter))$obj->KoeretoejMotorKmPerLiter = (float)$obj->KoeretoejMotorKmPerLiter;
		if(isset($obj->KoeretoejMotorStandStoej))$obj->KoeretoejMotorStandStoej = (float)$obj->KoeretoejMotorStandStoej;
		if(isset($obj->KoeretoejMotorKoerselStoej))$obj->KoeretoejMotorKoerselStoej = (float)$obj->KoeretoejMotorKoerselStoej;
		
		//bools
		if(isset($obj->KoeretoejMiljoeOplysningPartikelFilter))$obj->KoeretoejMiljoeOplysningPartikelFilter = ($obj->KoeretoejMiljoeOplysningPartikelFilter === 'true');
		if(isset($obj->KoeretoejOplysningTilkoblingMulighed))$obj->KoeretoejOplysningTilkoblingMulighed = ($obj->KoeretoejOplysningTilkoblingMulighed === 'true');
		if(isset($obj->KoeretoejOplysningEgnetTilTaxi))$obj->KoeretoejOplysningEgnetTilTaxi = ($obj->KoeretoejOplysningEgnetTilTaxi === 'true');
		if(isset($obj->KoeretoejOplysningNCAPTest))$obj->KoeretoejOplysningNCAPTest = ($obj->KoeretoejOplysningNCAPTest === 'true');
		if(isset($obj->KoeretoejOplysning30PctVarevogn))$obj->KoeretoejOplysning30PctVarevogn = ($obj->KoeretoejOplysning30PctVarevogn === 'true');
		if(isset($obj->KoeretoejMotorInnovativTeknik))$obj->KoeretoejMotorInnovativTeknik = ($obj->KoeretoejMotorInnovativTeknik === 'true');
		
		//Dates
		if(isset($obj->KoeretoejOplysningFoersteRegistreringDato))$obj->KoeretoejOplysningFoersteRegistreringDato = new MongoDate(strtotime($obj->KoeretoejOplysningFoersteRegistreringDato));
		if(isset($obj->KoeretoejOplysningStatusDato))$obj->KoeretoejOplysningStatusDato = new MongoDate(strtotime($obj->KoeretoejOplysningStatusDato));
		if(isset($obj->SynResultatStruktur->SynResultatSynsDato))$obj->SynResultatStruktur->SynResultatSynsDato = new MongoDate(strtotime($obj->SynResultatStruktur->SynResultatSynsDato));
		if(isset($obj->SynResultatStruktur->SynResultatSynStatusDato))$obj->SynResultatStruktur->SynResultatSynStatusDato = new MongoDate(strtotime($obj->SynResultatStruktur->SynResultatSynStatusDato));
		if(isset($obj->KoeretoejRegistreringStatusDato))$obj->KoeretoejRegistreringStatusDato = new MongoDate(strtotime($obj->KoeretoejRegistreringStatusDato));
		if(isset($obj->LeasingGyldigFra))$obj->LeasingGyldigFra = new MongoDate(strtotime($obj->LeasingGyldigFra));
		if(isset($obj->LeasingGyldigTil))$obj->LeasingGyldigTil = new MongoDate(strtotime($obj->LeasingGyldigTil));
		if(isset($obj->RegistreringNummerRettighedGyldigFra))$obj->RegistreringNummerRettighedGyldigFra = new MongoDate(strtotime($obj->RegistreringNummerRettighedGyldigFra));
		if(isset($obj->RegistreringNummerRettighedGyldigTil))$obj->RegistreringNummerRettighedGyldigTil = new MongoDate(strtotime($obj->RegistreringNummerRettighedGyldigTil));
		
		
		
		
		//removing redundant data
		unset($obj->KoeretoejMaerkeTypeNavn);
		unset($obj->KoeretoejModelTypeNavn);
		unset($obj->KoeretoejVariantTypeNavn);
		unset($obj->KarrosseriTypeNavn);
		
		
		
		//debug info
		if($this->debug){
			if($this->debug_show_objects){
				echo 'Object after processing:<br>';
				var_dump($obj);
			}		
		}
		
		// return the processed object
		return $obj;
			
	}
	
	protected function repack($parentobj , $child){
		$arraychild = (array)$child;
		foreach($arraychild as $key => $val){
			$parentobj->$key = $val;
		}
		return $parentobj;
	}
	
	protected function save($data, $database, $collection ){
		
		//Sanitizing names
		$database = strtolower(str_replace(' ', '_', $database));
		$database = str_replace('æ', 'ae', $database);
		$database = str_replace('ø', 'oe', $database);
		$database = str_replace('å', 'aa', $database);
		$collection = strtolower(str_replace(' ', '_', $collection));
		$collection = str_replace('æ', 'ae', $collection);
		$collection = str_replace('ø', 'oe', $collection);
		$collection = str_replace('å', 'aa', $collection);
		
		// select a database
		$db = $this->mongocon->$database;
		
		// select a collection (analogous to a relational database's table)
		$coll = $db->$collection;
		
		// add/save a record - using save to overwrite if it all ready exists
		$coll->save($data);
		
	}
	
	protected function do_we_want_this($obj){
		$we_want_it = false;
		
		//do we have a filter
		if(!is_array($this->collect_filter)) $we_want_it = true;
		//does the filter contain this type
		if(is_array($this->collect_filter) && in_array($obj->KoeretoejArtNavn,$this->collect_filter)){
		  $we_want_it = true;
		}
		//do we want registered only and do we have a licensnumber
		if($this->collect_registered_only && !isset($obj->RegistreringNummerNummer)){
			$we_want_it = false;	
		}
		
		return $we_want_it;
		
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
				  	$this->save($processed,$processed->KoeretoejArtNavn,'vehicles');
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