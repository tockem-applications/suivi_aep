<?php
class Reseau {
    private $id;
    private $nom;
    private $abreviation;
    private $date_creation;
    private $description_reseau;
    private $id_aep;

    public function __construct($id, $nom, $abreviation, $date_creation, $description_reseau, $id_aep) {
        $this->id = $id;
        $this->nom = $nom;
        $this->abreviation = $abreviation;
        $this->date_creation = $date_creation;
        $this->description_reseau = $description_reseau;
        $this->id_aep = $id_aep;
    }

    // Getters
    public function getId() { return $this->id; }
    public function getNom() { return $this->nom; }
    public function getAbreviation() { return $this->abreviation; }
    public function getDateCreation() { return $this->date_creation; }
    public function getDescriptionReseau() { return $this->description_reseau; }
    public function getIdAep() { return $this->id_aep; }

    // Setters
    public function setNom($nom) { $this->nom = $nom; }
    public function setAbreviation($abreviation) { $this->abreviation = $abreviation; }
    public function setDateCreation($date_creation) { $this->date_creation = $date_creation; }
    public function setDescriptionReseau($description_reseau) { $this->description_reseau = $description_reseau; }
    public function setIdAep($id_aep) { $this->id_aep = $id_aep; }
}
?>