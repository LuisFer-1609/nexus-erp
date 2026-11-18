<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Datos por defecto del volcado nexuserp.sql (13 tablas, 238 filas).
     * Los ids se conservan para que las llaves foráneas sigan siendo válidas.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
INSERT INTO `business_types` (`id`, `code`, `name`, `descripcion`, `created_at`, `created_user_id`, `updated_at`, `updated_user_id`, `deleted_at`, `deleted_user_id`) VALUES
(1, 'COM', 'Comercio', 'Compra y venta de productos', '2026-10-22 17:35:41', NULL, NULL, NULL, NULL, NULL),
(2, 'SRV', 'Servicios', 'Prestación de servicios profesionales', '2026-10-22 17:35:41', NULL, NULL, NULL, NULL, NULL),
(3, 'MAN', 'Manufactura', 'Fabricación o transformación de productos', '2026-10-22 17:35:41', NULL, NULL, NULL, NULL, NULL),
(4, 'TEC', 'Tecnología', 'Desarrollo de software y servicios digitales', '2026-10-22 17:35:41', NULL, NULL, NULL, NULL, NULL);

INSERT INTO `companies` (`id`, `name`, `business_name`, `rfc`, `type_of_business`, `mail`, `phone`, `address`, `is_active`) VALUES
(1, 'Nexus Interno (Pruebas)', 'Nexus Pruebas Internas S.A. de C.V.', 'XAXX010101000', 'Software', 'nexuserp@gmail.com', '0000000000', 'Dirección de pruebas', 1);

INSERT INTO `incident_types` (`id`, `code`, `name`, `description`, `is_paid`, `created_at`, `created_user_id`, `updated_at`, `updated_user_id`, `deleted_at`, `deleted_user_id`) VALUES
(1, 'VAC', 'Vacaciones', 'Periodo vacacional', 1, '2026-10-22 17:35:41', NULL, NULL, NULL, NULL, NULL),
(2, 'INC', 'Incapacidad', 'Incapacidad médica', 1, '2026-10-22 17:35:41', NULL, NULL, NULL, NULL, NULL),
(3, 'PCG', 'Permiso con goce', 'Permiso autorizado con sueldo', 1, '2026-10-22 17:35:41', NULL, NULL, NULL, NULL, NULL),
(4, 'PSG', 'Permiso sin goce', 'Permiso autorizado sin sueldo', 0, '2026-10-22 17:35:41', NULL, NULL, NULL, NULL, NULL),
(5, 'FJ', 'Falta justificada', 'Falta con justificante', 0, '2026-10-22 17:35:41', NULL, NULL, NULL, NULL, NULL);

INSERT INTO `lead_sources` (`id`, `code`, `name`, `created_at`, `created_user_id`, `updated_at`, `updated_user_id`, `deleted_at`, `deleted_user_id`) VALUES
(1, 'facebook', 'Facebook', '2026-10-22 17:35:41', NULL, NULL, NULL, NULL, NULL),
(2, 'instagram', 'Instagram', '2026-10-22 17:35:41', NULL, NULL, NULL, NULL, NULL);

INSERT INTO `lead_stages` (`id`, `code`, `name`, `position`, `created_at`, `created_user_id`, `updated_at`, `updated_user_id`, `deleted_at`, `deleted_user_id`) VALUES
(1, 'nuevo', 'Nuevo', 1, '2026-10-22 17:35:41', NULL, NULL, NULL, NULL, NULL),
(2, 'contactado', 'Contactado', 2, '2026-10-22 17:35:41', NULL, NULL, NULL, NULL, NULL),
(3, 'negociacion', 'Negociación', 3, '2026-10-22 17:35:41', NULL, NULL, NULL, NULL, NULL),
(4, 'cerrado', 'Cerrado', 4, '2026-10-22 17:35:41', NULL, NULL, NULL, NULL, NULL);

INSERT INTO `nationalities` (`id`, `nationality`, `c_pais`, `country`, `created_at`, `created_user_id`, `updated_at`, `updated_user_id`, `deleted_at`, `deleted_user_id`) VALUES
(1, 'Afgana', 'AFG', 'Afganistán', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(2, 'Albanesa', 'ALB', 'Albania', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(3, 'Alemana', 'DEU', 'Alemania', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(4, 'Andorrana', 'AND', 'Andorra', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(5, 'Angoleña', 'AGO', 'Angola', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(6, 'Antiguana y barbudense', 'ATG', 'Antigua y Barbuda', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(7, 'Saudí', 'SAU', 'Arabia Saudita', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(8, 'Argelina', 'DZA', 'Argelia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(9, 'Argentina', 'ARG', 'Argentina', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(10, 'Armenia', 'ARM', 'Armenia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(11, 'Australiana', 'AUS', 'Australia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(12, 'Austriaca', 'AUT', 'Austria', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(13, 'Azerbaiyana', 'AZE', 'Azerbaiyán', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(14, 'Bahameña', 'BHS', 'Bahamas', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(15, 'Bangladesí', 'BGD', 'Bangladés', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(16, 'Barbadense', 'BRB', 'Barbados', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(17, 'Bareiní', 'BHR', 'Baréin', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(18, 'Belga', 'BEL', 'Bélgica', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(19, 'Beliceña', 'BLZ', 'Belice', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(20, 'Beninesa', 'BEN', 'Benín', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(21, 'Bielorrusa', 'BLR', 'Bielorrusia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(22, 'Birmana', 'MMR', 'Birmania (Myanmar)', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(23, 'Boliviana', 'BOL', 'Bolivia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(24, 'Bosnia y herzegovina', 'BIH', 'Bosnia y Herzegovina', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(25, 'Botsuana', 'BWA', 'Botsuana', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(26, 'Brasileña', 'BRA', 'Brasil', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(27, 'Bruneana', 'BRN', 'Brunéi', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(28, 'Búlgara', 'BGR', 'Bulgaria', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(29, 'Burkinesa', 'BFA', 'Burkina Faso', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(30, 'Burundesa', 'BDI', 'Burundi', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(31, 'Butanesa', 'BTN', 'Bután', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(32, 'Caboverdiana', 'CPV', 'Cabo Verde', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(33, 'Camboyana', 'KHM', 'Camboya', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(34, 'Camerunesa', 'CMR', 'Camerún', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(35, 'Canadiense', 'CAN', 'Canadá', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(36, 'Catarí', 'QAT', 'Catar', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(37, 'Chadiana', 'TCD', 'Chad', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(38, 'Chilena', 'CHL', 'Chile', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(39, 'China', 'CHN', 'China', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(40, 'Chipriota', 'CYP', 'Chipre', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(41, 'Colombiana', 'COL', 'Colombia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(42, 'Comorense', 'COM', 'Comoras', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(43, 'Norcoreana', 'PRK', 'Corea del Norte', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(44, 'Surcoreana', 'KOR', 'Corea del Sur', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(45, 'Marfileña', 'CIV', 'Costa de Marfil', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(46, 'Costarricense', 'CRI', 'Costa Rica', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(47, 'Croata', 'HRV', 'Croacia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(48, 'Cubana', 'CUB', 'Cuba', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(49, 'Danesa', 'DNK', 'Dinamarca', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(50, 'Dominiquesa', 'DMA', 'Dominica', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(51, 'Ecuatoriana', 'ECU', 'Ecuador', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(52, 'Egipcia', 'EGY', 'Egipto', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(53, 'Salvadoreña', 'SLV', 'El Salvador', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(54, 'Emiratí', 'ARE', 'Emiratos Árabes Unidos', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(55, 'Eritrea', 'ERI', 'Eritrea', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(56, 'Eslovaca', 'SVK', 'Eslovaquia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(57, 'Eslovena', 'SVN', 'Eslovenia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(58, 'Española', 'ESP', 'España', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(59, 'Estadounidense', 'USA', 'Estados Unidos', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(60, 'Estonia', 'EST', 'Estonia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(61, 'Etíope', 'ETH', 'Etiopía', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(62, 'Filipina', 'PHL', 'Filipinas', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(63, 'Finlandesa', 'FIN', 'Finlandia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(64, 'Fiyiana', 'FJI', 'Fiyi', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(65, 'Francesa', 'FRA', 'Francia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(66, 'Gabonesa', 'GAB', 'Gabón', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(67, 'Gambiana', 'GMB', 'Gambia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(68, 'Georgiana', 'GEO', 'Georgia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(69, 'Ghanesa', 'GHA', 'Ghana', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(70, 'Granadina', 'GRD', 'Granada', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(71, 'Griega', 'GRC', 'Grecia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(72, 'Guatemalteca', 'GTM', 'Guatemala', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(73, 'Guineana', 'GIN', 'Guinea', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(74, 'Ecuatoguineana', 'GNQ', 'Guinea Ecuatorial', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(75, 'Guineana bisauguineana', 'GNB', 'Guinea-Bisáu', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(76, 'Guyanesa', 'GUY', 'Guyana', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(77, 'Haitiana', 'HTI', 'Haití', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(78, 'Hondureña', 'HND', 'Honduras', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(79, 'Húngara', 'HUN', 'Hungría', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(80, 'India', 'IND', 'India', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(81, 'Indonesia', 'IDN', 'Indonesia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(82, 'Iraquí', 'IRQ', 'Irak', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(83, 'Iraní', 'IRN', 'Irán', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(84, 'Irlandesa', 'IRL', 'Irlanda', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(85, 'Islandesa', 'ISL', 'Islandia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(86, 'Marshalesa', 'MHL', 'Islas Marshall', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(87, 'Salomonense', 'SLB', 'Islas Salomón', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(88, 'Israelí', 'ISR', 'Israel', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(89, 'Italiana', 'ITA', 'Italia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(90, 'Jamaiquina', 'JAM', 'Jamaica', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(91, 'Japonesa', 'JPN', 'Japón', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(92, 'Jordana', 'JOR', 'Jordania', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(93, 'Kazaja', 'KAZ', 'Kazajistán', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(94, 'Keniana', 'KEN', 'Kenia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(95, 'Kirguisa', 'KGZ', 'Kirguistán', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(96, 'Kiribatiana', 'KIR', 'Kiribati', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(97, 'Kuwaití', 'KWT', 'Kuwait', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(98, 'Laosiana', 'LAO', 'Laos', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(99, 'Lesotense', 'LSO', 'Lesoto', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(100, 'Letona', 'LVA', 'Letonia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(101, 'Libanesa', 'LBN', 'Líbano', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(102, 'Liberiana', 'LBR', 'Liberia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(103, 'Libia', 'LBY', 'Libia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(104, 'Liechtensteiniana', 'LIE', 'Liechtenstein', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(105, 'Lituana', 'LTU', 'Lituania', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(106, 'Luxemburguesa', 'LUX', 'Luxemburgo', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(107, 'Macedonia del Norte', 'MKD', 'Macedonia del Norte', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(108, 'Malgache', 'MDG', 'Madagascar', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(109, 'Malasia', 'MYS', 'Malasia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(110, 'Malauí', 'MWI', 'Malaui', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(111, 'Maldiva', 'MDV', 'Maldivas', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(112, 'Maliense', 'MLI', 'Malí', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(113, 'Maltesa', 'MLT', 'Malta', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(114, 'Marroquí', 'MAR', 'Marruecos', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(115, 'Mauriciana', 'MUS', 'Mauricio', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(116, 'Mauritana', 'MRT', 'Mauritania', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(117, 'Micronesia', 'FSM', 'Micronesia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(118, 'Moldava', 'MDA', 'Moldavia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(119, 'Monegasca', 'MCO', 'Mónaco', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(120, 'Mongola', 'MNG', 'Mongolia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(121, 'Montenegrina', 'MNE', 'Montenegro', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(122, 'Mozambiqueña', 'MOZ', 'Mozambique', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(123, 'Mexicana', 'MEX', 'México', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(124, 'Namibia', 'NAM', 'Namibia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(125, 'Nauruana', 'NRU', 'Nauru', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(126, 'Nepalí', 'NPL', 'Nepal', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(127, 'Nicaragüense', 'NIC', 'Nicaragua', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(128, 'Nigerina', 'NER', 'Níger', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(129, 'Nigeriana', 'NGA', 'Nigeria', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(130, 'Noruega', 'NOR', 'Noruega', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(131, 'Neozelandesa', 'NZL', 'Nueva Zelanda', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(132, 'Omaní', 'OMN', 'Omán', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(133, 'Neerlandesa', 'NLD', 'Países Bajos', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(134, 'Pakistaní', 'PAK', 'Pakistán', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(135, 'Palauana', 'PLW', 'Palaos', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(136, 'Palestina', 'PSE', 'Palestina', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(137, 'Panameña', 'PAN', 'Panamá', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(138, 'Papú', 'PNG', 'Papúa Nueva Guinea', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(139, 'Paraguaya', 'PRY', 'Paraguay', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(140, 'Peruana', 'PER', 'Perú', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(141, 'Polaca', 'POL', 'Polonia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(142, 'Portuguesa', 'PRT', 'Portugal', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(143, 'Británica', 'GBR', 'Reino Unido', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(144, 'Centroafricana', 'CAF', 'República Centroafricana', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(145, 'Checa', 'CZE', 'República Checa', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(146, 'Congoleña', 'COG', 'República del Congo', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(147, 'Congoleña (RDC)', 'COD', 'República Democrática del Congo', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(148, 'Dominicana', 'DOM', 'República Dominicana', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(149, 'Ruandesa', 'RWA', 'Ruanda', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(150, 'Rumana', 'ROU', 'Rumanía', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(151, 'Rusa', 'RUS', 'Rusia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(152, 'Samoana', 'WSM', 'Samoa', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(153, 'Sancristobaleña', 'KNA', 'San Cristóbal y Nieves', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(154, 'Sanmarinense', 'SMR', 'San Marino', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(155, 'Sanvicentina', 'VCT', 'San Vicente y las Granadinas', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(156, 'Santalucense', 'LCA', 'Santa Lucía', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(157, 'Santotomense', 'STP', 'Santo Tomé y Príncipe', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(158, 'Senegalesa', 'SEN', 'Senegal', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(159, 'Serbia', 'SRB', 'Serbia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(160, 'Seychellense', 'SYC', 'Seychelles', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(161, 'Sierraleonesa', 'SLE', 'Sierra Leona', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(162, 'Singapurense', 'SGP', 'Singapur', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(163, 'Siria', 'SYR', 'Siria', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(164, 'Somalí', 'SOM', 'Somalia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(165, 'Ceilanesa', 'LKA', 'Sri Lanka', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(166, 'Suazi', 'SWZ', 'Esuatini (Suazilandia)', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(167, 'Sudafricana', 'ZAF', 'Sudáfrica', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(168, 'Sudanesa', 'SDN', 'Sudán', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(169, 'Sursudanesa', 'SSD', 'Sudán del Sur', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(170, 'Sueca', 'SWE', 'Suecia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(171, 'Suiza', 'CHE', 'Suiza', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(172, 'Surinamesa', 'SUR', 'Surinam', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(173, 'Tailandesa', 'THA', 'Tailandia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(174, 'Taiwanesa', 'TWN', 'Taiwán', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(175, 'Tanzana', 'TZA', 'Tanzania', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(176, 'Tayika', 'TJK', 'Tayikistán', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(177, 'Timorense', 'TLS', 'Timor Oriental', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(178, 'Togolesa', 'TGO', 'Togo', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(179, 'Tongana', 'TON', 'Tonga', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(180, 'Trinitense', 'TTO', 'Trinidad y Tobago', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(181, 'Tunecina', 'TUN', 'Túnez', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(182, 'Turcomana', 'TKM', 'Turkmenistán', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(183, 'Turca', 'TUR', 'Turquía', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(184, 'Tuvaluana', 'TUV', 'Tuvalu', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(185, 'Ucraniana', 'UKR', 'Ucrania', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(186, 'Ugandesa', 'UGA', 'Uganda', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(187, 'Uruguaya', 'URY', 'Uruguay', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(188, 'Uzbeka', 'UZB', 'Uzbekistán', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(189, 'Vanuatuense', 'VUT', 'Vanuatu', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(190, 'Vaticana', 'VAT', 'Ciudad del Vaticano', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(191, 'Venezolana', 'VEN', 'Venezuela', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(192, 'Vietnamita', 'VNM', 'Vietnam', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(193, 'Yemení', 'YEM', 'Yemen', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(194, 'Yibutiana', 'DJI', 'Yibuti', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(195, 'Zambiana', 'ZMB', 'Zambia', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL),
(196, 'Zimbabuense', 'ZWE', 'Zimbabue', '2026-10-22 17:19:52', NULL, NULL, NULL, NULL, NULL);

INSERT INTO `payroll_concept_types` (`id`, `code`, `name`, `created_at`, `created_user_id`, `updated_at`, `updated_user_id`, `deleted_at`, `deleted_user_id`) VALUES
(1, 'percepcion', 'Percepción', '2026-10-22 17:35:41', NULL, NULL, NULL, NULL, NULL),
(2, 'deduccion', 'Deducción', '2026-10-22 17:35:41', NULL, NULL, NULL, NULL, NULL);

INSERT INTO `payroll_concepts` (`id`, `company_id`, `payroll_concept_type_id`, `code`, `name`, `is_active`, `created_at`, `created_user_id`, `updated_at`, `updated_user_id`, `deleted_at`, `deleted_user_id`) VALUES
(1, 1, 1, 'SUE', 'Sueldo', 1, '2026-10-22 17:35:41', NULL, NULL, NULL, NULL, NULL),
(2, 1, 1, 'BON', 'Bono', 1, '2026-10-22 17:35:41', NULL, NULL, NULL, NULL, NULL),
(3, 1, 2, 'ISR', 'ISR', 1, '2026-10-22 17:35:41', NULL, NULL, NULL, NULL, NULL),
(4, 1, 2, 'IMSS', 'IMSS', 1, '2026-10-22 17:35:41', NULL, NULL, NULL, NULL, NULL);

INSERT INTO `payroll_frequencies` (`id`, `code`, `name`, `days`, `created_at`, `created_user_id`, `updated_at`, `updated_user_id`, `deleted_at`, `deleted_user_id`) VALUES
(1, 'semanal', 'Semanal', 7, '2026-10-22 17:35:41', NULL, NULL, NULL, NULL, NULL),
(2, 'quincenal', 'Quincenal', 15, '2026-10-22 17:35:41', NULL, NULL, NULL, NULL, NULL),
(3, 'mensual', 'Mensual', 30, '2026-10-22 17:35:41', NULL, NULL, NULL, NULL, NULL);

INSERT INTO `roles` (`id`, `name`, `created_at`, `created_user_id`, `updated_at`, `updated_user_id`, `deleted_at`, `deleted_user_id`) VALUES
(1, 'Administrador', '2026-10-22 17:35:40', NULL, NULL, NULL, NULL, NULL),
(2, 'Recursos Humanos', '2026-10-22 17:35:40', NULL, NULL, NULL, NULL, NULL),
(3, 'Marketing', '2026-10-22 17:35:40', NULL, NULL, NULL, NULL, NULL),
(4, 'General', '2026-10-22 17:35:40', NULL, NULL, NULL, NULL, NULL);

INSERT INTO `users` (`id`, `company_id`, `role_id`, `nickname`, `name`, `last_name`, `phone`, `passwd`, `picture`, `is_active`, `is_admin`, `created_at`, `created_user_id`, `updated_at`, `updated_user_id`, `deleted_at`, `deleted_user_id`) VALUES
(1, 1, 1, 'admin', 'Administrador', 'Sistema', '0000000000', '$2y$12$KApXozJTMhPnKJa/.zjxcuVpxkwQdYmgs.CoFlzzUiFUUdAQDsurG', NULL, 1, 1, '2026-10-22 17:35:41', NULL, NULL, NULL, NULL, NULL);

INSERT INTO `areas` (`id`, `company_id`, `code`, `name`, `created_at`, `created_user_id`, `updated_at`, `updated_user_id`, `deleted_at`, `deleted_user_id`) VALUES
(6, 1, 'RH', 'Recursos Humanos', '2026-10-22 17:10:27', NULL, NULL, NULL, NULL, NULL),
(7, 1, 'MKT', 'Marketing', '2026-10-22 17:10:27', NULL, NULL, NULL, NULL, NULL),
(8, 1, 'VTA', 'Ventas', '2026-10-22 17:10:27', NULL, NULL, NULL, NULL, NULL),
(9, 1, 'ADM', 'Administración', '2026-10-22 17:10:27', NULL, NULL, NULL, NULL, NULL),
(10, 1, 'GRAL', 'General', '2026-10-22 17:10:27', NULL, NULL, NULL, NULL, NULL);

INSERT INTO `positions` (`id`, `company_id`, `area_id`, `code`, `name`, `created_at`, `created_user_id`, `updated_at`, `updated_user_id`, `deleted_at`, `deleted_user_id`) VALUES
(1, 1, 9, 'DIR-GRAL', 'Director general', '2026-10-22 17:16:48', NULL, NULL, NULL, NULL, NULL),
(2, 1, 6, 'GER-RH', 'Gerente de Recursos Humanos', '2026-10-22 17:16:48', NULL, NULL, NULL, NULL, NULL),
(3, 1, 6, 'AUX-RH', 'Auxiliar de Recursos Humanos', '2026-10-22 17:16:48', NULL, NULL, NULL, NULL, NULL),
(4, 1, 7, 'GER-MKT', 'Gerente de Marketing', '2026-10-22 17:16:48', NULL, NULL, NULL, NULL, NULL),
(5, 1, 8, 'GER-VTA', 'Gerente de Ventas', '2026-10-22 17:16:48', NULL, NULL, NULL, NULL, NULL),
(6, 1, 8, 'EJE-VTA', 'Ejecutivo de ventas', '2026-10-22 17:16:48', NULL, NULL, NULL, NULL, NULL),
(7, 1, 9, 'AUX-ADM', 'Auxiliar administrativo', '2026-10-22 17:16:48', NULL, NULL, NULL, NULL, NULL);
SQL);
    }

    public function down(): void
    {
        DB::table('positions')->whereIn('id', [1, 2, 3, 4, 5, 6, 7])->delete();
        DB::table('areas')->whereIn('id', [6, 7, 8, 9, 10])->delete();
        DB::table('users')->whereIn('id', [1])->delete();
        DB::table('roles')->whereIn('id', [1, 2, 3, 4])->delete();
        DB::table('payroll_frequencies')->whereIn('id', [1, 2, 3])->delete();
        DB::table('payroll_concepts')->whereIn('id', [1, 2, 3, 4])->delete();
        DB::table('payroll_concept_types')->whereIn('id', [1, 2])->delete();
        DB::table('nationalities')->whereIn('id', [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 49, 50, 51, 52, 53, 54, 55, 56, 57, 58, 59, 60, 61, 62, 63, 64, 65, 66, 67, 68, 69, 70, 71, 72, 73, 74, 75, 76, 77, 78, 79, 80, 81, 82, 83, 84, 85, 86, 87, 88, 89, 90, 91, 92, 93, 94, 95, 96, 97, 98, 99, 100, 101, 102, 103, 104, 105, 106, 107, 108, 109, 110, 111, 112, 113, 114, 115, 116, 117, 118, 119, 120, 121, 122, 123, 124, 125, 126, 127, 128, 129, 130, 131, 132, 133, 134, 135, 136, 137, 138, 139, 140, 141, 142, 143, 144, 145, 146, 147, 148, 149, 150, 151, 152, 153, 154, 155, 156, 157, 158, 159, 160, 161, 162, 163, 164, 165, 166, 167, 168, 169, 170, 171, 172, 173, 174, 175, 176, 177, 178, 179, 180, 181, 182, 183, 184, 185, 186, 187, 188, 189, 190, 191, 192, 193, 194, 195, 196])->delete();
        DB::table('lead_stages')->whereIn('id', [1, 2, 3, 4])->delete();
        DB::table('lead_sources')->whereIn('id', [1, 2])->delete();
        DB::table('incident_types')->whereIn('id', [1, 2, 3, 4, 5])->delete();
        DB::table('companies')->whereIn('id', [1])->delete();
        DB::table('business_types')->whereIn('id', [1, 2, 3, 4])->delete();
    }
};
