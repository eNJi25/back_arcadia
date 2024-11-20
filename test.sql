CREATE DATABASE arcadia;
USE arcadia;

CREATE TABLE habitat(
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    image VARCHAR(255) NOT NULL
);

CREATE TABLE race(
    id INT PRIMARY KEY AUTO_INCREMENT,
    race VARCHAR(255) NOT NULL
);

CREATE TABLE animal(
    id INT PRIMARY KEY AUTO_INCREMENT,
    prenom VARCHAR(255) NOT NULL,
    date_dernier_repas DATE,
    quantite_dernier_repas FLOAT,
    nourriture VARCHAR(255),
    race_id INT,
    habitat_id INT,
    FOREIGN KEY (race_id) REFERENCES race(id),
    FOREIGN KEY (habitat_id) REFERENCES habitat(id)
);


INSERT INTO habitat(nom, description, image) VALUES('Savane', 'Habitat de la savane', 'savane.jpg');

INSERT INTO race(race)
VALUES('Lion');

INSERT INTO animal(prenom, race_id, habitat_id) 
VALUES('Simba', 1, 1);

SELECT * FROM animal;

UPDATE animal 
SET prenom = 'Mufasa' 
WHERE id = 1;

DELETE
FROM animal 
WHERE id = 1;

