-- MySQL Script generated by MySQL Workbench
-- Thu May  2 15:05:10 2024
-- Model: New Model    Version: 1.0
-- MySQL Workbench Forward Engineering

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------
-- Schema repository
-- -----------------------------------------------------

-- -----------------------------------------------------
-- Schema repository
-- -----------------------------------------------------
CREATE SCHEMA IF NOT EXISTS `repository` DEFAULT CHARACTER SET utf8 ;
USE `repository` ;

-- -----------------------------------------------------
-- Table `repository`.`logins`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `repository`.`users` (
  `loginId` INT NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(45) NOT NULL,
  `passwordhash` VARCHAR(72) NOT NULL,
  `registered` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  `sessionhash` VARCHAR(64) NULL,
  `sessionstart` TIMESTAMP NULL,
  PRIMARY KEY (`loginId`),
  UNIQUE INDEX `id_UNIQUE` (`loginId` ASC),
  UNIQUE INDEX `username_UNIQUE` (`username` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `repository`.`login_groups`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `repository`.`user_groups` (
  `groupId` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(45) NOT NULL,
  PRIMARY KEY (`groupId`),
  UNIQUE INDEX `groupId_UNIQUE` (`groupId` ASC),
  UNIQUE INDEX `name_UNIQUE` (`name` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `repository`.`permissions`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `repository`.`user_permissions` (
  `permissionId` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(45) NULL,
  `value` VARCHAR(45) NULL,
  `noPermFallback` INT NULL,
  `allowBypass` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`permissionId`),
  UNIQUE INDEX `permissionId_UNIQUE` (`permissionId` ASC),
  INDEX `fk_permissions_permissions1_idx` (`noPermFallback` ASC),
  CONSTRAINT `fk_permissions_permissions1`
    FOREIGN KEY (`noPermFallback`)
    REFERENCES `repository`.`user_permissions` (`permissionId`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `repository`.`group_has_permissions`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `repository`.`user_group_has_permissions` (
  `ghpId` INT NOT NULL AUTO_INCREMENT,
  `permissionId` INT NOT NULL,
  `groupId` INT NOT NULL,
  PRIMARY KEY (`ghpId`),
  UNIQUE INDEX `ghpId_UNIQUE` (`ghpId` ASC),
  INDEX `fk_group_has_permissions_permissions1_idx` (`permissionId` ASC),
  INDEX `fk_group_has_permissions_groups1_idx` (`groupId` ASC),
  CONSTRAINT `fk_group_has_permissions_permissions1`
    FOREIGN KEY (`permissionId`)
    REFERENCES `repository`.`user_permissions` (`permissionId`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_group_has_permissions_groups1`
    FOREIGN KEY (`groupId`)
    REFERENCES `repository`.`user_groups` (`groupId`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `repository`.`login_has_groups`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `repository`.`user_has_groups` (
  `lhgId` INT NOT NULL AUTO_INCREMENT,
  `groupId` INT NOT NULL,
  `loginId` INT NOT NULL,
  PRIMARY KEY (`lhgId`),
  UNIQUE INDEX `lhgId_UNIQUE` (`lhgId` ASC),
  INDEX `fk_login_has_groups_groups1_idx` (`groupId` ASC),
  INDEX `fk_login_has_groups_logins1_idx` (`loginId` ASC),
  CONSTRAINT `fk_login_has_groups_groups1`
    FOREIGN KEY (`groupId`)
    REFERENCES `repository`.`user_groups` (`groupId`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_login_has_groups_logins1`
    FOREIGN KEY (`loginId`)
    REFERENCES `repository`.`user` (`loginId`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
