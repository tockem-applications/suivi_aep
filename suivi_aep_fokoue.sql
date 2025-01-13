

create database suivi_aep_fokoue;

use suivi_aep_fokoue;

create table reseau(
    id int(3) UNSIGNED AUTO_INCREMENT primary key,
    nom varchar(32) not null unique,
    abreviation varchar(16),
    date_creation date not null,
    description_reseau TEXT(512) null
)engine=innoDB;


create table abone(
    id int(4) UNSIGNED AUTO_INCREMENT primary key, 
    nom varchar(128) not null,
    numero_telephone varchar(16) not null,
    numero_compte_anticipation varchar(16) not null,
    etat varchar(10) not null,
    rang int(4),
    id_reseau int(3) UNSIGNED not null, 
    FOREIGN KEY (id_reseau) REFERENCES reseau(id)
) engine = innoDB;

create table constante_reseau (
    id int(2) UNSIGNED AUTO_INCREMENT primary key, 
    prix_metre_cube_eau int(5) UNSIGNED not null,
    prix_entretient_compteur int(5) UNSIGNED not null,
    prix_tva DECIMAL(7, 2) UNSIGNED not null,
    date_creation date not null,
    est_actif boolean not null,
    description TEXT(512) null
)engine = innoDB;


create table mois_facturation(
    id int(3) UNSIGNED AUTO_INCREMENT primary key, 
    mois varchar(32) unique not null,
    date_facturation date not null,
    date_depot date not null,
    id_constante int(2) UNSIGNED not null,
    description TEXT(512) null,
    est_actif boolean not null,
    FOREIGN key (id_constante) REFERENCES constante_reseau(id)
)engine= innoDB;


create table facture(
    id int(4) UNSIGNED AUTO_INCREMENT primary key, 
    ancien_index DECIMAL(7, 2) UNSIGNED not null,
    nouvel_index DECIMAL(7, 2) UNSIGNED not null,
    impaye DECIMAL(8, 2) default 0,
    montant_verse DECIMAL(8, 2) default 0,
    date_paiement date null,
    penalite DECIMAL(8, 2) default 0,
    id_mois_facturation int(3) UNSIGNED not null,
    id_abone int(4) UNSIGNED not null,
    message TEXT(512) null,
    FOREIGN key (id_mois_facturation) REFERENCES mois_facturation(id) on delete cascade,
    FOREIGN key (id_abone) REFERENCES abone(id) on delete cascade
)engine = innoDB;


update constante_reseau set est_actif=false where est_actif=true;

alter table constante_reseau
    modify prix_tva DECIMAL(7, 2) UNSIGNED not null;


alter table abone
    add column derniers_index DECIMAL(7, 2) UNSIGNED default 0;

alter table abone
    modify derniers_index DECIMAL(7, 2) UNSIGNED default 0;

alter table mois_facturation
    modify mois varchar(32) unique not null;


select a.id,  a.nom as libele, numero_compteur as numero, numero_telephone as numero_abone, derniers_index as ancien_index, 0.0 as nouvel_index, r.nom as reseau, 0.0 as latitude, 0.0 as longitude, Date_format(now(), '%d/%m/%y') as date_releve from abone a, reseau r where a.id_reseau = r.id;

select id_abone, impaye, penalite, prix_entretient_compteur,
    prix_metre_cube_eau, prix_tva, montant_verse, nouvel_index, ancien_index
    from facture f, constante_reseau c, mois_facturation m 
    where m.id_constante = c.id and 
        f.id_mois_facturation = m.id and m.est_actif=true;


select f.id, f.id_abone, a.nom, f.ancien_index, f.nouvel_index, c.prix_entretient_compteur, 
    c.prix_metre_cube_eau, c.prix_tva, penalite, impaye, f.montant_verse, f.date_paiement
    from abone a, facture f, constante_reseau c 
    where f.id_mois_facturation =45 and c.id=5 and a.id=f.id_abone order by a.id;

--  Pour chaque abone on cherche le derniers mois de facturation
select id_abone, impaye, penalite, prix_entretient_compteur,
    prix_metre_cube_eau, prix_tva, montant_verse, nouvel_index, ancien_index, max(mois)
    from facture f, constante_reseau c, mois_facturation m 
    where m.id_constante = c.id and 
        f.id_mois_facturation = m.id group by f.id_abone



select * from mois_facturation order mois desc;


--Ma20 t$mberl0te


select f.* from abone a, facture f, mois_facturation  m, reseau r
    where a.id_reseau = r.id and f.id_abone=a.id and f.id_mois_facturation=m.id a.id = 1;


select f.*, m.*  from abone a, facture f, mois_facturation m
    where a.id=1 and f.id_abone=a.id and f.id_mois_facturation = m.id;


select f.id, f.id_abone, a.nom, m.mois, f.ancien_index, f.nouvel_index, c.prix_entretient_compteur, 
    c.prix_metre_cube_eau, c.prix_tva, penalite, impaye, f.montant_verse, f.date_paiement, date_depot, date_facturation, r.nom reseau
    from abone a, facture f, constante_reseau c, mois_facturation m, reseau r 
    where f.id_mois_facturation =$id_mois and c.id=$id_constante and a.id=f.id_abone order by a.id m.id=$id_mois;


select f.id, f.id_abone, a.nom, m.mois, f.ancien_index, f.nouvel_index, c.prix_entretient_compteur, 
    c.prix_metre_cube_eau, c.prix_tva, penalite, impaye, f.montant_verse, f.date_paiement, date_depot, date_facturation, r.nom reseau
    from abone a, facture f, constante_reseau c, mois_facturation m, reseau r 
    where f.id_mois_facturation =m.id and c.id=m.id_constante and a.id=f.id_abone and m.id=65 and r.id = a.id_reseau order by a.id;


select m.* mois, count(f.id) anombre_abone, sum(montant_verse) total_verse, sum(nouvel_index-ancien_index) total_conso from mois_facturation m, facture f
    where m.id = 72 and m.id = f.id_mois_facturation;

select * from facture where id_mois_facturation=72

select a.id, a.nom, a.numero_compteur, r.nom reseau, f.impaye, f.montant_verse, m.mois, sum(nouvel_index -ancien_index) total_conso, sum(montant_verse) montant_verse from abone a, mois_facturation m, reseau r, facture f
    where a.id = 6 and a.id_reseau = r.id and a.id=f.id_abone and m.id = f.id_mois_facturation;



select f.*, m.mois from abone a, mois_facturation m, reseau r, facture f
    where a.id = 6 and a.id_reseau = r.id and a.id=f.id_abone and m.id = f.id_mois_facturation;



update abone a, facture f set derniers_index = d_index where f.id_abone = a.id and f.id_mois_facturation = max(id_mois_facturation) 

select a.nom, count(f.id) duree, r.nom reseau, numero_telephone, sum(nouvel_index - ancien_index) conasommation, derniers_index, sum(impaye)impaye,  numero_compteur, sum(montant_verse) montant_verse, max(date_paiement)date_paiement, a.etat from abone a, facture f, reseau r 
    where a.id = f.id_abone and r.id = a.id_reseau and a.id = 7;

select m.*, f.id, count(f.id) nombre, sum(f.montant_verse) montant_versee,
       sum(f.nouvel_index-f.ancien_index) conso
    from mois_facturation m, facture f, constante_reseau c
    where m.id = f.id_mois_facturation and c.id = m.id_constante
    group by m.id;




update abone, facture, mois_facturation
    set abone.derniers_index = facture.nouvel_index
    where mois = '2025-08' and abone.id=facture.id_abone and facture.id_mois_facturation = mois_facturation.id;

select distinct (a.id), a.nom, f.montant_verse, f.nouvel_index-f.ancien_index as conso, m.id
    from facture f, mois_facturation m, abone a
    where m.mois='2024-03' and a.id = f.id_abone and f.id_mois_facturation = m.id
    group by a.id;


SELECT
    mf.mois,
    a.id AS abonne_id,
    a.nom AS abonne_nom,
    a.numero_compteur,
    r.nom,
    derniers_index, ancien_index, nouvel_index,
    nouvel_index-ancien_index conso,
    prix_metre_cube_eau, prix_entretient_compteur, c.prix_tva,
    f.montant_verse,
    f.impaye,
    f.penalite
FROM
    abone a
        CROSS JOIN
    mois_facturation mf
        LEFT JOIN
    facture f ON f.id_mois_facturation = mf.id AND f.id_abone = a.id
        INNER JOIN reseau r on a.id_reseau = r.id
        INNER JOIN constante_reseau c on mf.id_constante = c.id
where
    mf.mois >='2024-03' and mf.mois <= '2024-12'
ORDER BY
    mf.mois, a.id;




select m.mois, montant_verse, i.montant as impaye, prix_tva, prix_entretient_compteur, prix_metre_cube_eau
    from abone a
    inner join facture f on a.id = f.id_abone
    left join impaye i on f.id = i.id_facture
    inner join mois_facturation m on f.id_mois_facturation = m.id
    inner join constante_reseau c on m.id_constante = c.id
    where a.id = 20;


alter table abone
    add column type_compteur varchar(16) default 'distribution';




create table aep(
    id integer(4) unsigned primary key auto_increment,
    libele varchar(64) not null ,
    description text(1000)
)engine = innodb;

insert into aep value ('', 'Fokoue', 'Il s\'agit de l\'aep de la commune de fokoue');

create table redevance(
    id integer(4) unsigned primary key auto_increment,
    libele varchar(64) not null ,
    pourcentage decimal(5, 2) not null,
    description text(1000),
    id_aep integer(4) unsigned,
    constraint fk_aep_redevance foreign key (id_aep) references aep(id)
)engine = innodb;

create table role(
     id integer(3) unsigned primary key auto_increment,
     role varchar(32) not null unique
)engine=innodb;

create table travail(
    id integer(5) unsigned primary key auto_increment,
    id_aep integer(4) unsigned not null,
    id_utilisateur integer(4) unsigned not null,
    constraint fk_aep_travail foreign key (id_aep) references aep(id),
    constraint fk_utilisateur_travail foreign key (id_utilisateur) references utilisateur(id)
)engine=innodb;

create table avoir_role(
   id integer(5) unsigned primary key auto_increment,
   id_role integer(4) unsigned not null,
   id_utilisateur integer(4) unsigned not null,
   constraint fk_role_avoir_role foreign key (id_role) references aep(id),
   constraint fk_utilisateur_avoir_role foreign key (id_utilisateur) references utilisateur(id)
)engine=innodb;

create table utilisateur(
    id integer(5) unsigned primary key auto_increment,
    email varchar(32) not null ,
    nom varchar(32) not null ,
    prenom varchar(32) not null,
    numero_telephone varchar(16) not null
)engine=innodb;


create table compteur(
    id integer(5) unsigned primary key auto_increment,
    numero_comteur varchar(16) not null,
    longitude decimal(12, 6),
    latitude decimal(12, 6),
    dernier_index decimal(7,2) not null,
    description text(1000)
) engine = innoDB;

alter table compteur
    change dernier_index derniers_index decimal(7, 2) not null;

alter table compteur
    change numero_comteur numero_compteur varchar(16) not null;

create table compteur_abone(
   id_abone integer(5) unsigned not null,
   id_compteur integer(5) unsigned not null,
   constraint fk_abone_compteur_abone foreign key (id_abone) references abone(id),
   constraint fk_compteur_compteur_abone foreign key (id_compteur) references compteur(id)
)engine=innoDB;

create table position_compteur_aep(
    id integer(2) unsigned primary key auto_increment,
    position varchar(32) not null
)engine = innoDB;


create table compteur_aep(
   id_aep integer(5) unsigned not null,
   id_compteur integer(5) unsigned not null,
   id_position integer(3) unsigned not null,
   constraint fk_aep_compteur_aep foreign key (id_aep) references aep(id),
   constraint fk_aep_compteur_aep foreign key (id_aep) references aep(id),
   constraint fk_compteur_compteur_abone foreign key (id_compteur) references compteur(id)
)engine=innoDB;

create table compteur_reseau(
       id_reseau integer(5) unsigned not null,
       id_compteur integer(5) unsigned not null,
       constraint fk_reseau_compteur_reseau foreign key (id_reseau) references reseau(id),
       constraint fk_compteur_compteur_abone foreign key (id_compteur) references compteur(id)
)engine=innoDB;

alter table reseau
    drop column id_aep;

alter table reseau
    add column id_aep integer(4) unsigned not null default 1,
    add constraint fk_aep_reseau foreign key (id_aep) references aep(id);
alter table constante_reseau
    add column id_aep integer(4) unsigned not null default 1,
    add constraint fk_aep_constante foreign key (id_aep) references aep(id);

alter table reseau
    drop index nom;

alter table mois_facturation
    drop index mois;

update mois_facturation m
    inner join constante_reseau c on m.id_constante = c.id
    set m.est_actif=false
    where m.est_actif=true and c.id_aep=?










