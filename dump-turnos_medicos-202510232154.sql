-- MySQL dump 10.13  Distrib 8.0.19, for Win64 (x86_64)
--
-- Host: localhost    Database: turnos_medicos
-- ------------------------------------------------------
-- Server version	8.4.3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `especialidad`
--

DROP TABLE IF EXISTS `especialidad`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `especialidad` (
  `Id_Especialidad` int NOT NULL AUTO_INCREMENT,
  `Nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Descripcion` text COLLATE utf8mb4_unicode_ci,
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`Id_Especialidad`),
  UNIQUE KEY `nombre_UNIQUE` (`Nombre`),
  KEY `idx_activo` (`Activo`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `especialidad`
--

LOCK TABLES `especialidad` WRITE;
/*!40000 ALTER TABLE `especialidad` DISABLE KEYS */;
INSERT INTO `especialidad` VALUES (1,'Clínica Médica','Atención médica general',1),(2,'Pediatría','Atención pediátrica',1),(3,'Cardiología','Enfermedades cardiovasculares',1),(4,'Traumatología','Sistema músculo-esquelético',1),(5,'Ginecología','Salud de la mujer',1),(6,'Dermatología','Enfermedades de la piel',1),(7,'Oftalmología','Enfermedades oculares',1);
/*!40000 ALTER TABLE `especialidad` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `medico`
--

DROP TABLE IF EXISTS `medico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `medico` (
  `Id_medico` int NOT NULL AUTO_INCREMENT,
  `Legajo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Id_usuario` int NOT NULL,
  `Id_Especialidad` int NOT NULL,
  `Dias_Disponibles` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'lunes,martes,miercoles,jueves,viernes',
  `Hora_Inicio` time DEFAULT '08:00:00',
  `Hora_Fin` time DEFAULT '16:00:00',
  `Duracion_Turno` int DEFAULT '30',
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`Id_medico`),
  UNIQUE KEY `uniq_medico_usuario` (`Id_usuario`),
  UNIQUE KEY `legajo_UNIQUE` (`Legajo`),
  KEY `medico_usuario_FK` (`Id_usuario`),
  KEY `medico_especialidad_FK` (`Id_Especialidad`),
  KEY `idx_activo` (`Activo`),
  KEY `idx_especialidad_activo` (`Id_Especialidad`,`Activo`),
  CONSTRAINT `medico_especialidad_FK` FOREIGN KEY (`Id_Especialidad`) REFERENCES `especialidad` (`Id_Especialidad`) ON DELETE RESTRICT,
  CONSTRAINT `medico_usuario_FK` FOREIGN KEY (`Id_usuario`) REFERENCES `usuario` (`Id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medico`
--

LOCK TABLES `medico` WRITE;
/*!40000 ALTER TABLE `medico` DISABLE KEYS */;
INSERT INTO `medico` VALUES (3,'195-b',18,3,'lunes,viernes','08:00:00','16:00:00',30,1);
/*!40000 ALTER TABLE `medico` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `paciente`
--

DROP TABLE IF EXISTS `paciente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paciente` (
  `Id_paciente` int NOT NULL AUTO_INCREMENT,
  `Obra_social` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Sin obra social',
  `Libreta_sanitaria` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N/A',
  `Id_usuario` int NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  `Fecha_Alta` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_paciente`),
  UNIQUE KEY `uniq_paciente_usuario` (`Id_usuario`),
  KEY `paciente_usuario_FK` (`Id_usuario`),
  KEY `idx_activo` (`Activo`),
  CONSTRAINT `paciente_usuario_FK` FOREIGN KEY (`Id_usuario`) REFERENCES `usuario` (`Id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `paciente`
--

LOCK TABLES `paciente` WRITE;
/*!40000 ALTER TABLE `paciente` DISABLE KEYS */;
INSERT INTO `paciente` VALUES (9,'OSDE','6789',19,1,'2025-10-24 00:39:30');
/*!40000 ALTER TABLE `paciente` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `secretaria`
--

DROP TABLE IF EXISTS `secretaria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `secretaria` (
  `Id_secretaria` int NOT NULL AUTO_INCREMENT,
  `Id_usuario` int NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  `Fecha_Alta` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_secretaria`),
  UNIQUE KEY `uniq_secretaria_usuario` (`Id_usuario`),
  KEY `secretaria_usuario_FK` (`Id_usuario`),
  KEY `idx_activo` (`Activo`),
  CONSTRAINT `secretaria_usuario_FK` FOREIGN KEY (`Id_usuario`) REFERENCES `usuario` (`Id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `secretaria`
--

LOCK TABLES `secretaria` WRITE;
/*!40000 ALTER TABLE `secretaria` DISABLE KEYS */;
INSERT INTO `secretaria` VALUES (4,14,1,'2025-10-24 00:30:00'),(5,17,1,'2025-10-24 00:35:40');
/*!40000 ALTER TABLE `secretaria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `turno`
--

DROP TABLE IF EXISTS `turno`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `turno` (
  `Id_turno` int NOT NULL AUTO_INCREMENT,
  `Fecha` datetime NOT NULL,
  `Estado` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'reservado',
  `Id_paciente` int NOT NULL,
  `Id_medico` int NOT NULL,
  `Id_secretaria` int DEFAULT NULL,
  `Observaciones` text COLLATE utf8mb4_unicode_ci,
  `Fecha_Creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `Fecha_Modificacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_turno`),
  KEY `turno_paciente_FK` (`Id_paciente`),
  KEY `turno_medico_FK` (`Id_medico`),
  KEY `turno_secretaria_FK` (`Id_secretaria`),
  KEY `idx_fecha` (`Fecha`),
  KEY `idx_estado` (`Estado`),
  KEY `idx_medico_fecha` (`Id_medico`,`Fecha`),
  KEY `idx_paciente_fecha` (`Id_paciente`,`Fecha`),
  CONSTRAINT `turno_medico_FK` FOREIGN KEY (`Id_medico`) REFERENCES `medico` (`Id_medico`) ON DELETE RESTRICT,
  CONSTRAINT `turno_paciente_FK` FOREIGN KEY (`Id_paciente`) REFERENCES `paciente` (`Id_paciente`) ON DELETE RESTRICT,
  CONSTRAINT `turno_secretaria_FK` FOREIGN KEY (`Id_secretaria`) REFERENCES `secretaria` (`Id_secretaria`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `turno`
--

LOCK TABLES `turno` WRITE;
/*!40000 ALTER TABLE `turno` DISABLE KEYS */;
/*!40000 ALTER TABLE `turno` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuario`
--

DROP TABLE IF EXISTS `usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario` (
  `Id_usuario` int NOT NULL AUTO_INCREMENT,
  `Nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Apellido` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dni` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Contraseña` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Rol` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `Fecha_Registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_usuario`),
  UNIQUE KEY `email_UNIQUE` (`email`),
  UNIQUE KEY `dni_UNIQUE` (`dni`),
  KEY `idx_rol` (`Rol`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario`
--

LOCK TABLES `usuario` WRITE;
/*!40000 ALTER TABLE `usuario` DISABLE KEYS */;
INSERT INTO `usuario` VALUES (14,'Maria','Gonzalez','48165176','secretaria@clinica.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','secretaria','2025-10-24 00:30:00'),(17,'lucia','gomez','48176154','pepe@gmail.com','$2y$10$bL5cJeA9ZIu1OxcgTpM/WOQIuXpn2ee.ev69S/RvmXv6Al3Kyj.Xq','secretaria','2025-10-24 00:35:40'),(18,'braian','salgado','4867585','braiansalgado2436@gmail.com','$2y$10$6j81cIFfIjP6n6Ia3gFMkeQnVb81GW0kZP/2Z4lKd9fZzQ8Gbr7i.','medico','2025-10-24 00:36:45'),(19,'Juan','Perez','48234567','sadafabcsafa@gmail.com','$2y$12$gzniafBVGmltUPva6ovmfeh9R4zK4gmOqvwrId5Vz63LhHxvcrRCe','paciente','2025-10-24 00:39:30');
/*!40000 ALTER TABLE `usuario` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'turnos_medicos'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-10-23 21:54:40
