CREATE DATABASE IF NOT EXISTS db_turnamen;
USE db_turnamen;

CREATE TABLE pemain (
  id_pemain INT AUTO_INCREMENT PRIMARY KEY,
  nama_lengkap VARCHAR(100) NOT NULL,
  nickname VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(100) NOT NULL UNIQUE,
  kata_sandi VARCHAR(255) NOT NULL,
  peran ENUM('admin','pemain') NOT NULL DEFAULT 'pemain'
) ENGINE=InnoDB;

CREATE TABLE tim (
  id_tim INT AUTO_INCREMENT PRIMARY KEY,
  id_kapten INT NOT NULL,
  nama_tim VARCHAR(100) NOT NULL,
  logo VARCHAR(255),
  FOREIGN KEY (id_kapten) REFERENCES pemain(id_pemain)
) ENGINE=InnoDB;

CREATE TABLE anggota_tim (
  id_anggota INT AUTO_INCREMENT PRIMARY KEY,
  id_tim INT NOT NULL,
  id_pemain INT NOT NULL,
  posisi VARCHAR(30),
  UNIQUE (id_tim, id_pemain),
  FOREIGN KEY (id_tim) REFERENCES tim(id_tim) ON DELETE CASCADE,
  FOREIGN KEY (id_pemain) REFERENCES pemain(id_pemain) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE game (
  id_game INT AUTO_INCREMENT PRIMARY KEY,
  nama_game VARCHAR(100) NOT NULL,
  platform VARCHAR(30)
) ENGINE=InnoDB;

CREATE TABLE turnamen (
  id_turnamen INT AUTO_INCREMENT PRIMARY KEY,
  id_game INT NOT NULL,
  nama_turnamen VARCHAR(150) NOT NULL,
  tgl_mulai DATE NOT NULL,
  tgl_selesai DATE NOT NULL,
  total_hadiah DECIMAL(12,2) DEFAULT 0,
  status ENUM('pendaftaran','berlangsung','selesai') NOT NULL DEFAULT 'pendaftaran',
  FOREIGN KEY (id_game) REFERENCES game(id_game)
) ENGINE=InnoDB;

CREATE TABLE pendaftaran (
  id_pendaftaran INT AUTO_INCREMENT PRIMARY KEY,
  id_turnamen INT NOT NULL,
  id_tim INT NOT NULL,
  tgl_daftar DATE NOT NULL,
  status_bayar ENUM('belum','lunas') NOT NULL DEFAULT 'belum',
  UNIQUE (id_turnamen, id_tim),
  FOREIGN KEY (id_turnamen) REFERENCES turnamen(id_turnamen) ON DELETE CASCADE,
  FOREIGN KEY (id_tim) REFERENCES tim(id_tim) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE pertandingan (
  id_pertandingan INT AUTO_INCREMENT PRIMARY KEY,
  id_turnamen INT NOT NULL,
  id_tim_a INT NOT NULL,
  id_tim_b INT NOT NULL,
  id_tim_menang INT NULL,
  skor_a INT DEFAULT 0,
  skor_b INT DEFAULT 0,
  waktu_main DATETIME NOT NULL,
  babak VARCHAR(50),
  FOREIGN KEY (id_turnamen) REFERENCES turnamen(id_turnamen) ON DELETE CASCADE,
  FOREIGN KEY (id_tim_a) REFERENCES tim(id_tim),
  FOREIGN KEY (id_tim_b) REFERENCES tim(id_tim),
  FOREIGN KEY (id_tim_menang) REFERENCES tim(id_tim)
) ENGINE=InnoDB;
