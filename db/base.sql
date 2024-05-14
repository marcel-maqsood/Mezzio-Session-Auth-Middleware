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
-- Table `repository`.`user_groups`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `repository`.`user_groups` (
  `groupId` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(45) NOT NULL,
  `hidden` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`groupId`),
  UNIQUE INDEX `id_UNIQUE` (`groupId` ASC) VISIBLE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `repository`.`user_permissions`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `repository`.`user_permissions` (
  `permissionId` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `value` VARCHAR(255) NOT NULL,
  `noPermFallback` INT NULL,
  `allowBypass` INT NOT NULL DEFAULT 0,
  `hidden` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`permissionId`),
  UNIQUE INDEX `id_UNIQUE` (`permissionId` ASC) VISIBLE,
  INDEX `fk_user_permissions_user_permissions1_idx` (`noPermFallback` ASC) VISIBLE,
  CONSTRAINT `fk_user_permissions_user_permissions1`
    FOREIGN KEY (`noPermFallback`)
    REFERENCES `repository`.`user_permissions` (`permissionId`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `repository`.`user_group_has_permissions`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `repository`.`user_group_has_permissions` (
  `ghpId` INT NOT NULL AUTO_INCREMENT,
  `groupId` INT NOT NULL,
  `permissionId` INT NOT NULL,
  PRIMARY KEY (`ghpId`),
  INDEX `fk_user_group_has_permission_user_groups1_idx` (`groupId` ASC) VISIBLE,
  INDEX `fk_user_group_has_permission_user_permissions1_idx` (`permissionId` ASC) VISIBLE,
  UNIQUE INDEX `id_UNIQUE` (`ghpId` ASC) VISIBLE,
  CONSTRAINT `fk_user_group_has_permission_user_groups1`
    FOREIGN KEY (`groupId`)
    REFERENCES `repository`.`user_groups` (`groupId`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_user_group_has_permission_user_permissions1`
    FOREIGN KEY (`permissionId`)
    REFERENCES `repository`.`user_permissions` (`permissionId`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `repository`.`users`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `repository`.`users` (
  `loginId` INT NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(45) NOT NULL,
  `email` VARCHAR(450) NULL,
  `passwordhash` VARCHAR(64) NOT NULL,
  `registered` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `sessionhash` VARCHAR(64) NULL,
  `sessionstart` TIMESTAMP NULL,
  `hidden` INT NULL DEFAULT 0,
  `forgothash` VARCHAR(64) NOT NULL DEFAULT 'test',
  `forgotvalid` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`loginId`),
  UNIQUE INDEX `id_UNIQUE` (`loginId` ASC) INVISIBLE,
  UNIQUE INDEX `hidden_UNIQUE` (`hidden` ASC) VISIBLE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `repository`.`user_has_groups`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `repository`.`user_has_groups` (
  `lhgId` INT NOT NULL AUTO_INCREMENT,
  `groupId` INT NOT NULL,
  `loginId` INT NOT NULL,
  PRIMARY KEY (`lhgId`),
  UNIQUE INDEX `id_UNIQUE` (`lhgId` ASC) VISIBLE,
  INDEX `fk_user_has_groups_user_groups1_idx` (`groupId` ASC) VISIBLE,
  INDEX `fk_user_has_groups_users1_idx` (`loginId` ASC) VISIBLE,
  CONSTRAINT `fk_user_has_groups_user_groups1`
    FOREIGN KEY (`groupId`)
    REFERENCES `repository`.`user_groups` (`groupId`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_user_has_groups_users1`
    FOREIGN KEY (`loginId`)
    REFERENCES `repository`.`users` (`loginId`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
