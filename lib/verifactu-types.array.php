<?php
/* Copyright (C) 2025 Alberto SuperAdmin <aluquerivasdev@gmail.com>
 * Copyright (C) 2025 Germán Luis Aracil Boned <garacilb@gmail.com>
 *
 * Based on original code from verifactu module by Alberto SuperAdmin (easysoft.es)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    verifactu/lib/verifactu-types.array.php
 * \ingroup verifactu
 * \brief   Arrays with VeriFactu data types according to AEAT specifications
 */

/**
 * Tax Types (AEAT code L1)
 * Defines the type of indirect tax applied to the invoice
 */
$taxTypes = array(
	"01" => '(01) Impuesto sobre el Valor Añadido (IVA)',
	"02" => '(02) Impuesto sobre la Producción. Los Servicios y la Importación (IPSI) de Ceuta y Melilla',
	"03" => '(03) Impuesto General Indirecto Canario (IGIC)',
	"05" => '(05) Otros',
);

/**
 * Invoice Types (AEAT code L2)
 * Defines the type of invoice according to RD 1619/2012
 */
$invoiceTypes = array(
	"F1" => '(F1) Factura (art. 6 o 7.2 o 7.3 del RD 1619/2012)',
	"F2" => '(F2) Factura Simplificada y Facturas sin identificación del destinatario art. 6.1.d) RD 1619/2012',
	"F3" => '(F3) Factura emitida en sustitución de facturas simplificadas facturadas y declaradas',
	"R1" => '(R1) Factura Rectificativa (Error fundado en derecho y Art. 80 Uno Dos y Seis LIVA)',
	"R2" => '(R2) Factura Rectificativa (Art. 80.3)',
	"R3" => '(R3) Factura Rectificativa (Art. 80.4)',
	"R4" => '(R4) Factura Rectificativa (Resto)',
	"R5" => '(R5) Factura Rectificativa en facturas simplificadas',
);

/**
 * VAT Regime Keys (AEAT code L8)
 * Defines special VAT calculation or reporting rules
 */
$regimeKeys = array(
	"01" => '(01) Operación de régimen general',
	"02" => '(02) Exportación',
	"03" => '(03) Operaciones a las que se aplique el régimen especial de bienes usados. Objetos de arte.Antigüedades y objetos de colección',
	"04" => '(04) Régimen especial del oro de inversión',
	"05" => '(05) Régimen especial de las agencias de viajes',
	"06" => '(06) Régimen especial grupo de entidades (IVA: Nivel Avanzado / IGIC: Nivel Avanzado)',
	"07" => '(07) Régimen especial del criterio de caja',
	"08" => '(08) Operaciones sujetas (IVA: al IPSI/IGIC / IGIC-IPSI: al IPSI/IVA)',
	"09" => '(09) Facturación de las prestaciones de servicios de agencias de viaje que actúan como mediadoras en nombre y por cuenta ajena (D.A.4ª RD1619/2012)',
	"10" => '(10) Cobros por cuenta de terceros de honorarios profesionales o de derechos derivados de la propiedad industrial. De autor u otros por cuenta de sus socios asociados o colegiados efectuados por sociedades. Asociaciones. Colegios profesionales u otras entidades que realicen estas funciones de cobro',
	"11" => '(11) Operaciones de arrendamiento de local de negocio',
	"14" => '(14) Factura con IVA/IGIC pendiente de devengo en certificaciones de obra cuyo destinatario sea una Administración Pública',
	"15" => '(15) Factura con IVA/IGIC pendiente de devengo en operaciones de tracto sucesivo',
	"17" => '(17) IVA: Operación acogida a alguno de los regímenes previstos en el Capítulo XI del Título IX (OSS e IOSS) / IGIC: Régimen especial de comerciante minorista',
	"18" => '(18) IVA: Recargo de equivalencia / IGIC: Régimen especial del pequeño empresario o profesional',
	"19" => '(19) IVA: Operaciones de actividades incluidas en el Régimen Especial de Agricultura. Ganadería y Pesca (REAGYP) / IGIC: Operaciones interiores exentas por aplicación artículo 25 Ley 19/1994',
	"20" => '(20) Régimen simplificado (solo IVA)'
);

/**
 * Operation Qualifications (AEAT code L9)
 * Determines VAT treatment of the operation
 */
$operationQualifications = array(
	"" => 'Automático (recomendado)',
	"S1" => '(S1) Operación Sujeta y No exenta - Sin inversión del sujeto pasivo',
	"S2" => '(S2) Operación Sujeta y No exenta - Con inversión del sujeto pasivo',
	"N1" => '(N1) Operación No Sujeta artículo 7. 14. otros',
	"N2" => '(N2) Operación No Sujeta por Reglas de localización'
);

/**
 * Exempt Operations (AEAT code L10)
 * Reason for VAT exemption
 */
$exemptOperations = array(
	"" => 'Automático (recomendado)',
	"E1" => '(E1) Exenta por el artículo 20',
	"E2" => '(E2) Exenta por el artículo 21',
	"E3" => '(E3) Exenta por el artículo 22',
	"E4" => '(E4) Exenta por los artículos 23 y 24',
	"E5" => '(E5) Exenta por el artículo 25',
	"E6" => '(E6) Exenta por otros'
);

/**
 * Identification Types (AEAT code L7)
 * For non-Spanish recipients
 */
$identificationTypes = array(
	"02" => '(02) NIF-IVA',
	"03" => '(03) Pasaporte',
	"04" => '(04) Documento oficial de identificación expedido por el país o territorio de residencia',
	"05" => '(05) Certificado de residencia',
	"06" => '(06) Otro documento probatorio',
	"07" => '(07) No censado',
);

/**
 * VeriFactu Incidence Flag
 * Indicates if the submission is due to a technical incidence
 */
$verifactuIncidence = array(
	"N" => '(N) Sin incidencia',
	"S" => '(S) Con incidencia técnica',
);

// =============================================================================
// BACKWARD COMPATIBILITY ALIASES
// These aliases maintain compatibility with existing code using Spanish names
// =============================================================================

$tipoImpuestos = &$taxTypes;
$tipoFacturas = &$invoiceTypes;
$claveRegimen = &$regimeKeys;
$calificacionOperacion = &$operationQualifications;
$operacionExenta = &$exemptOperations;
$tiposIdentificacion = &$identificationTypes;
$incidenciaVeriFactu = &$verifactuIncidence;
