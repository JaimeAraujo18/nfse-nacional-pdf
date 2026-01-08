<?php

namespace NfsePdf;

use TCPDF;

class NfsePdfGenerator
{
    private $pdf;
    private $data;

    public function __construct()
    {
        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->SetCreator('NFS-e PDF Generator');
        $this->pdf->SetAuthor('NFS-e System');
        $this->pdf->SetTitle('DANFSe');
        $this->pdf->SetSubject('Documento Auxiliar da NFS-e');
        $this->pdf->SetMargins(10, 10, 10);
        $this->pdf->SetAutoPageBreak(true, 10);
        $this->pdf->SetFont('helvetica', '', 8);
    }
    
    // Column positions based on PDF percentages (595px = 210mm page width)
    // 2.38%, 26.2%, 50.02%, 73.84% of 210mm = ~5mm, ~55mm, ~105mm, ~155mm from left edge
    // With 10mm margin: positions are absolute from page edge
    private function getColX($colNum)
    {
        $pageWidth = 210; // A4 width in mm
        $positions = [
            1 => $pageWidth * 0.0238,  // ~5mm from page edge
            2 => $pageWidth * 0.262,    // ~55mm from page edge
            3 => $pageWidth * 0.5002,   // ~105mm from page edge
            4 => $pageWidth * 0.7384    // ~155mm from page edge
        ];
        return $positions[$colNum] ?? 10;
    }

    public function parseXml($xmlFile)
    {
        $xml = simplexml_load_file($xmlFile);
        if ($xml === false) {
            throw new \Exception('Failed to parse XML file');
        }

        $ns = $xml->getNamespaces(true);
        $infNFSe = $xml->children($ns[''])->infNFSe;
        $dps = $infNFSe->children($ns[''])->DPS->children($ns[''])->infDPS;

        $id = (string)$infNFSe['Id'];
        // Remove "NFS" prefix from Id to get chave de acesso
        $chaveAcesso = preg_replace('/^NFS/', '', $id);
        
        $this->data = [
            'chaveAcesso' => $chaveAcesso,
            'numeroNfse' => (string)$infNFSe->nNFSe,
            'localEmissao' => (string)$infNFSe->xLocEmi,
            'localPrestacao' => (string)$infNFSe->xLocPrestacao,
            'localIncidencia' => (string)$infNFSe->xLocIncid,
            'tribNac' => (string)$infNFSe->xTribNac,
            'dataProcessamento' => $this->formatDateTime((string)$infNFSe->dhProc),
            'numeroDFSe' => (string)$infNFSe->nDFSe,
            'emitente' => [
                'cnpj' => $this->formatCnpjCpf((string)$infNFSe->emit->CNPJ),
                'nome' => (string)$infNFSe->emit->xNome,
                'logradouro' => (string)$infNFSe->emit->enderNac->xLgr,
                'numero' => (string)$infNFSe->emit->enderNac->nro,
                'bairro' => (string)$infNFSe->emit->enderNac->xBairro,
                'municipio' => (string)$infNFSe->emit->enderNac->cMun,
                'uf' => (string)$infNFSe->emit->enderNac->UF,
                'cep' => $this->formatCep((string)$infNFSe->emit->enderNac->CEP),
                'fone' => $this->formatPhone((string)$infNFSe->emit->fone),
                'email' => (string)$infNFSe->emit->email,
            ],
            'tomador' => [
                'cnpj' => $this->formatCnpjCpf((string)$dps->toma->CNPJ),
                'nome' => (string)$dps->toma->xNome,
                'logradouro' => (string)$dps->toma->end->endNac->xLgr,
                'numero' => (string)$dps->toma->end->endNac->nro,
                'complemento' => (string)$dps->toma->end->endNac->xCpl,
                'bairro' => (string)$dps->toma->end->endNac->xBairro,
                'municipio' => (string)$dps->toma->end->endNac->cMun,
                'cep' => $this->formatCep((string)$dps->toma->end->endNac->CEP),
            ],
            'servico' => [
                'codTribNac' => (string)$dps->serv->cServ->cTribNac,
                'descricao' => (string)$dps->serv->cServ->xDescServ,
            ],
            'valores' => [
                'valorServico' => (float)$dps->valores->vServPrest->vServ,
                'valorLiquido' => (float)$infNFSe->valores->vLiq,
                'valorTotalRet' => (float)$infNFSe->valores->vTotalRet,
            ],
            'dps' => [
                'numero' => (string)$dps->nDPS,
                'serie' => (string)$dps->serie,
                'competencia' => $this->formatDate((string)$dps->dCompet),
                'dataEmissao' => $this->formatDateTime((string)$dps->dhEmi),
            ],
            'tributacao' => [
                'tribISSQN' => (string)$dps->valores->trib->tribMun->tribISSQN,
                'tpRetISSQN' => (string)$dps->valores->trib->tribMun->tpRetISSQN,
                'totTribFed' => (float)$dps->valores->trib->totTrib->pTotTrib->pTotTribFed,
                'totTribEst' => (float)$dps->valores->trib->totTrib->pTotTrib->pTotTribEst,
                'totTribMun' => (float)$dps->valores->trib->totTrib->pTotTrib->pTotTribMun,
            ],
        ];

        return $this;
    }

    public function generate()
    {
        $this->pdf->AddPage();
        
        $this->addHeader();
        $this->addHorizontalLine();
        $this->addChaveAcesso();
        $this->addHorizontalLine();
        $this->addDadosNfse();
        $this->addHorizontalLine();
        $this->addEmitente();
        $this->addHorizontalLine();
        $this->addTomador();
        $this->addHorizontalLine();
        $this->addServico();
        $this->addHorizontalLine();
        $this->addTributacao();
        $this->addHorizontalLine();
        $this->addValores();
        $this->addHorizontalLine();
        $this->addTotaisTributos();

        return $this->pdf;
    }

    private function addHorizontalLine()
    {
        $y = $this->pdf->GetY();
        $this->pdf->Line(10, $y, 200, $y);
        $this->pdf->Ln(2);
    }

    private function addHeader()
    {
        $startY = $this->pdf->GetY();
        
        // Left column - Logo image
        $logoPath = __DIR__ . '/../assets/logo-nfse-assinatura-horizontal.png';
        if (file_exists($logoPath)) {
            $this->pdf->Image($logoPath, 10, $startY, 50, 0, 'PNG', '', '', false, 300, '', false, false, 0, false, false, false);
        }
        
        // Center column - Main title (37.81% from HTML = ~80mm from page edge = ~70mm from margin)
        $centerX = 70;
        $this->pdf->SetXY($centerX, $startY);
        $this->pdf->SetFont('helvetica', 'B', 9);
        $this->pdf->Cell(50, 4, 'DANFSe v1.0', 0, 0, 'C');
        $this->pdf->SetXY($centerX, $startY + 4);
        $this->pdf->SetFont('helvetica', 'B', 9);
        $this->pdf->Cell(50, 4, 'Documento Auxiliar da NFS-e', 0, 0, 'C');
        
        // Right column - Municipality info (73.84% from HTML = ~155mm from page edge = ~145mm from margin)
        $rightX = 145;
        $this->pdf->SetXY($rightX, $startY);
        $this->pdf->SetFont('helvetica', 'B', 8);
        $this->pdf->Cell(55, 3, 'Prefeitura Municipal de ' . $this->data['localEmissao'], 0, 1, 'R');
        $this->pdf->SetXY($rightX, $startY + 3);
        $this->pdf->SetFont('helvetica', '', 6);
        $this->pdf->Cell(55, 2.5, 'Secretaria Municipal da Fazenda', 0, 1, 'R');
        $this->pdf->SetXY($rightX, $startY + 5.5);
        $this->pdf->Cell(55, 2.5, '(48)3431-0074', 0, 1, 'R');
        $this->pdf->SetXY($rightX, $startY + 8);
        $this->pdf->Cell(55, 2.5, 'tributos@criciuma.sc.gov.br', 0, 1, 'R');
        
        // Move Y position down for next content
        $this->pdf->SetY($startY + 12);
        $this->pdf->Ln(1);
    }

    private function addChaveAcesso()
    {
        $this->pdf->SetFont('helvetica', 'B', 7);
        $this->pdf->Cell(0, 4, 'Chave de Acesso da NFS-e', 0, 1, 'L');
        $this->pdf->SetFont('helvetica', '', 8);
        // Table with border
        $this->pdf->Cell(0, 4, $this->data['chaveAcesso'], 0, 1, 'L');
        $this->pdf->Ln(1);
    }

    private function addDadosNfse()
    {
        // Column widths based on HTML structure
        $col1X = $this->getColX(1);
        $col2X = $this->getColX(2);
        $col3X = $this->getColX(3);
        $col4X = $this->getColX(4);
        $col1W = $col2X - $col1X;
        $col2W = $col3X - $col2X;
        $col3W = $col4X - $col3X;
        $col4W = 200 - $col4X; // Page width (210mm) minus right margin (10mm) minus col4 start
        
        // First row - NFS-e data (3 columns)
        $row1Y = $this->pdf->GetY();
        $this->pdf->SetFont('helvetica', 'B', 7);
        $this->pdf->SetXY($col1X, $row1Y);
        $this->pdf->Cell($col1W, 4, 'Número da NFS-e', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row1Y);
        $this->pdf->Cell($col2W, 4, 'Competência da NFS-e', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row1Y);
        $this->pdf->Cell($col3W, 4, 'Data e Hora da emissão da NFS-e', 0, 1, 'L');
        
        $this->pdf->SetFont('helvetica', '', 8);
        $this->pdf->SetXY($col1X, $row1Y + 4);
        $this->pdf->Cell($col1W, 4, $this->data['numeroNfse'], 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row1Y + 4);
        $this->pdf->Cell($col2W, 4, $this->data['dps']['competencia'], 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row1Y + 4);
        $this->pdf->Cell($col3W, 4, $this->data['dataProcessamento'], 0, 1, 'L');
        
        // Second row - DPS data with QR code in 4th column
        $row2Y = $this->pdf->GetY();
        $this->pdf->SetFont('helvetica', 'B', 7);
        $this->pdf->SetXY($col1X, $row2Y);
        $this->pdf->Cell($col1W, 4, 'Número da DPS', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row2Y);
        $this->pdf->Cell($col2W, 4, 'Série da DPS', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row2Y);
        $this->pdf->Cell($col3W, 4, 'Data e Hora da emissão da DPS', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row2Y);
        $this->pdf->Cell($col4W, 4, '', 0, 1, 'L'); // Empty header for QR code
        
        $this->pdf->SetFont('helvetica', '', 8);
        $this->pdf->SetXY($col1X, $row2Y + 4);
        $this->pdf->Cell($col1W, 4, $this->data['dps']['numero'], 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row2Y + 4);
        $this->pdf->Cell($col2W, 4, $this->data['dps']['serie'], 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row2Y + 4);
        $this->pdf->Cell($col3W, 4, $this->data['dps']['dataEmissao'], 0, 0, 'L');
        
        // QR Code cell - spans both header and data rows (no border)
        $qrCellHeight = 8; // Height of both rows
        // Note: No border drawn - matching original PDF style
        
        // Add QR code and message inside the cell
        $qrUrl = 'https://www.nfse.gov.br/ConsultaPublica?tpc=1&chave=' . $this->data['chaveAcesso'];
        $qrSize = 10;
        $qrX = $col4X + 1;
        $qrY = $row2Y + 1;
        
        $style = array(
            'border' => 0,
            'vpadding' => 'auto',
            'hpadding' => 'auto',
            'fgcolor' => array(0, 0, 0),
            'bgcolor' => false,
            'module_width' => 1,
            'module_height' => 1
        );
        
        $this->pdf->write2DBarcode($qrUrl, 'QRCODE,L', $qrX, $qrY, $qrSize, $qrSize, $style, 'N');
        
        // Authenticity message below QR code (positioned at right side)
        $messageX = $col4X;
        $messageY = $qrY + $qrSize + 0.5;
        $this->pdf->SetXY($messageX, $messageY);
        $this->pdf->SetFont('helvetica', '', 6);
        $message = 'A autenticidade desta NFS-e pode ser verificada pela leitura deste código QR ou pela consulta da chave de acesso no portal nacional da NFS-e';
        $this->pdf->MultiCell($col4W - 2, 2, $message, 0, 'L', false, 0, $messageX, $messageY);
        
        // Move to next line after the row
        $this->pdf->SetY($row2Y + $qrCellHeight);
        $this->pdf->Ln(1);
    }

    private function addEmitente()
    {
        $col1X = $this->getColX(1);
        $col2X = $this->getColX(2);
        $col3X = $this->getColX(3);
        $col4X = $this->getColX(4);
        $col1W = $col2X - $col1X;
        $col2W = $col3X - $col2X;
        $col3W = $col4X - $col3X;
        $col4W = 190 - $col4X;
        
        $this->pdf->SetFont('helvetica', 'B', 8);
        $this->pdf->Cell(0, 4, 'EMITENTE DA NFS-e', 0, 1, 'L');
        $this->pdf->SetFont('helvetica', '', 8);
        
        $emit = $this->data['emitente'];
        $startY = $this->pdf->GetY();
        
        // Header row
        $this->pdf->SetXY($col1X, $startY);
        $this->pdf->Cell($col1W, 4, 'Prestador do Serviço', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $startY);
        $this->pdf->SetFont('helvetica', 'B', 7);
        $this->pdf->Cell($col2W, 4, 'CNPJ / CPF / NIF', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $startY);
        $this->pdf->Cell($col3W, 4, 'Inscrição Municipal', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $startY);
        $this->pdf->Cell($col4W, 4, 'Telefone', 0, 1, 'L');
        
        // Data row
        $this->pdf->SetFont('helvetica', '', 8);
        $this->pdf->SetXY($col1X, $startY + 4);
        $this->pdf->Cell($col1W, 4, '', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $startY + 4);
        $this->pdf->Cell($col2W, 4, $emit['cnpj'], 0, 0, 'L');
        $this->pdf->SetXY($col3X, $startY + 4);
        $this->pdf->Cell($col3W, 4, '-', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $startY + 4);
        $this->pdf->Cell($col4W, 4, $emit['fone'], 0, 1, 'L');
        
        // Header row
        $row2Y = $this->pdf->GetY();
        $this->pdf->SetFont('helvetica', 'B', 7);
        $this->pdf->SetXY($col1X, $row2Y);
        $this->pdf->Cell($col1W, 4, 'Nome / Nome Empresarial', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row2Y);
        $this->pdf->Cell($col2W, 4, '', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row2Y);
        $this->pdf->Cell($col3W, 4, 'E-mail', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row2Y);
        $this->pdf->Cell($col4W, 4, '', 0, 1, 'L');
        
        // Data row
        $this->pdf->SetFont('helvetica', '', 8);
        $this->pdf->SetXY($col1X, $row2Y + 4);
        $this->pdf->Cell($col1W, 4, $emit['nome'], 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row2Y + 4);
        $this->pdf->Cell($col2W, 4, '', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row2Y + 4);
        $this->pdf->Cell($col3W, 4, $emit['email'], 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row2Y + 4);
        $this->pdf->Cell($col4W, 4, '', 0, 1, 'L');
        
        $endereco = $emit['logradouro'] . ', ' . $emit['numero'] . ', ' . $emit['bairro'];
        // Header row
        $row3Y = $this->pdf->GetY();
        $this->pdf->SetFont('helvetica', 'B', 7);
        $this->pdf->SetXY($col1X, $row3Y);
        $this->pdf->Cell($col1W, 4, 'Endereço', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row3Y);
        $this->pdf->Cell($col2W, 4, '', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row3Y);
        $this->pdf->Cell($col3W, 4, 'Município', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row3Y);
        $this->pdf->Cell($col4W, 4, 'CEP', 0, 1, 'L');
        
        // Data row
        $this->pdf->SetFont('helvetica', '', 8);
        $this->pdf->SetXY($col1X, $row3Y + 4);
        $this->pdf->Cell($col1W, 4, $endereco, 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row3Y + 4);
        $this->pdf->Cell($col2W, 4, '', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row3Y + 4);
        $this->pdf->Cell($col3W, 4, $this->data['localEmissao'] . ' - ' . $emit['uf'], 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row3Y + 4);
        $this->pdf->Cell($col4W, 4, $emit['cep'], 0, 1, 'L');
        
        $this->pdf->SetFont('helvetica', '', 7);
        $this->pdf->SetXY($col1X, $this->pdf->GetY());
        $this->pdf->Cell($col1W, 3, 'Simples Nacional na Data de Competência', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $this->pdf->GetY());
        $this->pdf->Cell($col3W + $col4W, 3, 'Regime de Apuração Tributária pelo SN', 0, 1, 'L');
        $this->pdf->SetXY($col1X, $this->pdf->GetY());
        $this->pdf->Cell($col1W, 3, 'Optante - Microempresa ou Empresa de Pequeno Porte (ME/EPP)', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $this->pdf->GetY());
        $this->pdf->Cell($col3W + $col4W, 3, 'Regime de apuração dos tributos federais e municipal pelo Simples Nacional', 0, 1, 'L');
        $this->pdf->Ln(1);
    }

    private function addTomador()
    {
        $this->pdf->SetFont('helvetica', 'B', 9);
        $this->pdf->Cell(0, 6, 'TOMADOR DO SERVIÇO', 0, 1, 'L');
        $this->pdf->SetFont('helvetica', '', 8);
        
        $toma = $this->data['tomador'];
        // Header row
        $this->pdf->Cell(60, 5, '', 0, 0, 'L');
        $this->pdf->Cell(50, 5, 'CNPJ / CPF / NIF', 0, 0, 'L');
        $this->pdf->Cell(40, 5, 'Inscrição Municipal', 0, 0, 'L');
        $this->pdf->Cell(40, 5, 'Telefone', 0, 1, 'L');
        
        // Data row
        $this->pdf->Cell(60, 5, '', 0, 0, 'L');
        $this->pdf->Cell(50, 5, $toma['cnpj'], 0, 0, 'L');
        $this->pdf->Cell(40, 5, '-', 0, 0, 'L');
        $this->pdf->Cell(40, 5, '', 0, 1, 'L');
        
        // Header row
        $this->pdf->Cell(60, 5, 'Nome / Nome Empresarial', 0, 0, 'L');
        $this->pdf->Cell(50, 5, '', 0, 0, 'L');
        $this->pdf->Cell(40, 5, 'E-mail', 0, 0, 'L');
        $this->pdf->Cell(40, 5, '', 0, 1, 'L');
        
        // Data row
        $this->pdf->Cell(60, 5, $toma['nome'], 0, 0, 'L');
        $this->pdf->Cell(50, 5, '', 0, 0, 'L');
        $this->pdf->Cell(40, 5, '-', 0, 0, 'L');
        $this->pdf->Cell(40, 5, '', 0, 1, 'L');
        
        $endereco = $toma['logradouro'] . ', ' . $toma['numero'];
        if (!empty($toma['complemento'])) {
            $endereco .= ', ' . $toma['complemento'];
        }
        $endereco .= ', ' . $toma['bairro'];
        
        // Header row
        $this->pdf->Cell(60, 5, 'Endereço', 0, 0, 'L');
        $this->pdf->Cell(50, 5, '', 0, 0, 'L');
        $this->pdf->Cell(40, 5, 'Município', 0, 0, 'L');
        $this->pdf->Cell(40, 5, 'CEP', 0, 1, 'L');
        
        // Data row
        $this->pdf->Cell(60, 5, $endereco, 0, 0, 'L');
        $this->pdf->Cell(50, 5, '', 0, 0, 'L');
        $this->pdf->Cell(40, 5, $this->data['localIncidencia'], 0, 0, 'L');
        $this->pdf->Cell(40, 5, $toma['cep'], 0, 1, 'L');
        $this->pdf->Ln(2);
        
        $this->pdf->SetFont('helvetica', 'B', 9);
        $this->pdf->Cell(0, 5, 'INTERMEDIÁRIO DO SERVIÇO NÃO IDENTIFICADO NA NFS-e', 0, 1, 'L');
        $this->pdf->Ln(2);
    }

    private function addServico()
    {
        $this->pdf->SetFont('helvetica', 'B', 9);
        $this->pdf->Cell(0, 6, 'SERVIÇO PRESTADO', 0, 1, 'L');
        $this->pdf->SetFont('helvetica', '', 8);
        
        $serv = $this->data['servico'];
        // Header row
        $this->pdf->Cell(60, 5, 'Código de Tributação Nacional', 0, 0, 'L');
        $this->pdf->Cell(50, 5, 'Código de Tributação Municipal', 0, 0, 'L');
        $this->pdf->Cell(40, 5, 'Local da Prestação', 0, 0, 'L');
        $this->pdf->Cell(40, 5, 'País da Prestação', 0, 1, 'L');
        
        // Data row - Format code as 01.03.02
        $codTribFormatted = $this->formatCodTribNac($serv['codTribNac']);
        $codTrib = $codTribFormatted . ' - ' . substr($this->data['tribNac'], 0, 50) . '...';
        $this->pdf->Cell(60, 5, $codTrib, 0, 0, 'L');
        $this->pdf->Cell(50, 5, '-', 0, 0, 'L');
        $this->pdf->Cell(40, 5, $this->data['localPrestacao'], 0, 0, 'L');
        $this->pdf->Cell(40, 5, '-', 0, 1, 'L');
        
        // Descrição
        $this->pdf->Cell(60, 5, 'Descrição do Serviço', 0, 0, 'L');
        $this->pdf->Cell(130, 5, $serv['descricao'], 0, 1, 'L');
        $this->pdf->Ln(2);
    }

    private function addTributacao()
    {
        $this->pdf->SetFont('helvetica', 'B', 9);
        $this->pdf->Cell(0, 6, 'TRIBUTAÇÃO MUNICIPAL', 0, 1, 'L');
        $this->pdf->SetFont('helvetica', '', 8);
        
        $trib = $this->data['tributacao'];
        // Header row
        $this->pdf->Cell(50, 5, 'Tributação do ISSQN', 0, 0, 'L');
        $this->pdf->Cell(50, 5, 'País Resultado da Prestação do Serviço', 0, 0, 'L');
        $this->pdf->Cell(50, 5, 'Município de Incidência do ISSQN', 0, 0, 'L');
        $this->pdf->Cell(40, 5, 'Regime Especial de Tributação', 0, 1, 'L');
        
        // Data row
        $this->pdf->Cell(50, 5, 'Operação Tributável', 0, 0, 'L');
        $this->pdf->Cell(50, 5, '-', 0, 0, 'L');
        $this->pdf->Cell(50, 5, $this->data['localIncidencia'], 0, 0, 'L');
        $this->pdf->Cell(40, 5, 'Nenhum', 0, 1, 'L');
        
        // Header row
        $this->pdf->Cell(50, 5, 'Tipo de Imunidade', 0, 0, 'L');
        $this->pdf->Cell(50, 5, 'Suspensão da Exigibilidade do ISSQN', 0, 0, 'L');
        $this->pdf->Cell(50, 5, 'Número Processo Suspensão', 0, 0, 'L');
        $this->pdf->Cell(40, 5, 'Benefício Municipal', 0, 1, 'L');
        
        // Data row
        $this->pdf->Cell(50, 5, '-', 0, 0, 'L');
        $this->pdf->Cell(50, 5, 'Não', 0, 0, 'L');
        $this->pdf->Cell(50, 5, '-', 0, 0, 'L');
        $this->pdf->Cell(40, 5, '-', 0, 1, 'L');
        
        $val = $this->data['valores'];
        // Header row
        $this->pdf->Cell(50, 5, 'Valor do Serviço', 0, 0, 'L');
        $this->pdf->Cell(50, 5, 'Desconto Incondicionado', 0, 0, 'L');
        $this->pdf->Cell(50, 5, 'Total Deduções/Reduções', 0, 0, 'L');
        $this->pdf->Cell(40, 5, 'Cálculo do BM', 0, 1, 'L');
        
        // Data row
        $this->pdf->Cell(50, 5, 'R$ ' . number_format($val['valorServico'], 2, ',', '.'), 0, 0, 'L');
        $this->pdf->Cell(50, 5, '-', 0, 0, 'L');
        $this->pdf->Cell(50, 5, '-', 0, 0, 'L');
        $this->pdf->Cell(40, 5, '-', 0, 1, 'L');
        
        // Header row
        $this->pdf->Cell(50, 5, 'BC ISSQN', 0, 0, 'L');
        $this->pdf->Cell(50, 5, 'Alíquota Aplicada', 0, 0, 'L');
        $this->pdf->Cell(50, 5, 'Retenção do ISSQN', 0, 0, 'L');
        $this->pdf->Cell(40, 5, 'ISSQN Apurado', 0, 1, 'L');
        
        // Data row
        $this->pdf->Cell(50, 5, '-', 0, 0, 'L');
        $this->pdf->Cell(50, 5, '-', 0, 0, 'L');
        $this->pdf->Cell(50, 5, 'Não Retido', 0, 0, 'L');
        $this->pdf->Cell(40, 5, '-', 0, 1, 'L');
        
        $this->pdf->SetFont('helvetica', 'B', 9);
        $this->pdf->Cell(0, 6, 'TRIBUTAÇÃO FEDERAL', 0, 1, 'L');
        $this->pdf->SetFont('helvetica', '', 8);
        
        // Header row
        $this->pdf->Cell(50, 5, 'IRRF', 0, 0, 'L');
        $this->pdf->Cell(50, 5, 'CP', 0, 0, 'L');
        $this->pdf->Cell(50, 5, 'CSLL', 0, 0, 'L');
        $this->pdf->Cell(40, 5, '', 0, 1, 'L');
        
        // Data row
        $this->pdf->Cell(50, 5, '-', 0, 0, 'L');
        $this->pdf->Cell(50, 5, '-', 0, 0, 'L');
        $this->pdf->Cell(50, 5, '-', 0, 0, 'L');
        $this->pdf->Cell(40, 5, '', 0, 1, 'L');
        
        // Header row
        $this->pdf->Cell(50, 5, 'PIS', 0, 0, 'L');
        $this->pdf->Cell(50, 5, 'COFINS', 0, 0, 'L');
        $this->pdf->Cell(50, 5, 'Retenção do PIS/COFINS', 0, 0, 'L');
        $this->pdf->Cell(40, 5, 'TOTAL TRIBUTAÇÃO FEDERAL', 0, 1, 'L');
        
        // Data row
        $this->pdf->Cell(50, 5, '-', 0, 0, 'L');
        $this->pdf->Cell(50, 5, '-', 0, 0, 'L');
        $this->pdf->Cell(50, 5, '-', 0, 0, 'L');
        $this->pdf->Cell(40, 5, '-', 0, 1, 'L');
        $this->pdf->Ln(2);
    }

    private function addValores()
    {
        $this->pdf->SetFont('helvetica', 'B', 9);
        $this->pdf->Cell(0, 6, 'VALOR TOTAL DA NFS-E', 0, 1, 'L');
        $this->pdf->SetFont('helvetica', '', 8);
        
        $val = $this->data['valores'];
        // Header row
        $this->pdf->Cell(50, 5, 'Valor do Serviço', 0, 0, 'L');
        $this->pdf->Cell(50, 5, 'Desconto Condicionado', 0, 0, 'L');
        $this->pdf->Cell(50, 5, 'Desconto Incondicionado', 0, 0, 'L');
        $this->pdf->Cell(40, 5, 'ISSQN Retido', 0, 1, 'L');
        
        // Data row
        $this->pdf->Cell(50, 5, 'R$ ' . number_format($val['valorServico'], 2, ',', '.'), 0, 0, 'L');
        $this->pdf->Cell(50, 5, '-', 0, 0, 'L');
        $this->pdf->Cell(50, 5, '-', 0, 0, 'L');
        $this->pdf->Cell(40, 5, '-', 0, 1, 'L');
        
        // Header row
        $this->pdf->Cell(50, 5, 'IRRF, CP,CSLL - Retidos', 0, 0, 'L');
        $this->pdf->Cell(50, 5, 'PIS/COFINS Retidos', 0, 0, 'L');
        $this->pdf->Cell(50, 5, '', 0, 0, 'L');
        $this->pdf->Cell(40, 5, 'Valor Líquido da NFS-e', 0, 1, 'L');
        
        // Data row
        $this->pdf->Cell(50, 5, 'R$ ' . number_format($val['valorTotalRet'], 2, ',', '.'), 0, 0, 'L');
        $this->pdf->Cell(50, 5, '-', 0, 0, 'L');
        $this->pdf->Cell(50, 5, '', 0, 0, 'L');
        $this->pdf->Cell(40, 5, 'R$ ' . number_format($val['valorLiquido'], 2, ',', '.'), 0, 1, 'L');
        $this->pdf->Ln(2);
    }

    private function addTotaisTributos()
    {
        $this->pdf->SetFont('helvetica', 'B', 9);
        $this->pdf->Cell(0, 6, 'TOTAIS APROXIMADOS DOS TRIBUTOS', 0, 1, 'L');
        $this->pdf->SetFont('helvetica', '', 8);
        
        $trib = $this->data['tributacao'];
        // Header row
        $this->pdf->Cell(60, 5, 'Federais', 0, 0, 'L');
        $this->pdf->Cell(60, 5, 'Estaduais', 0, 0, 'L');
        $this->pdf->Cell(60, 5, 'Municipais', 0, 1, 'L');
        
        // Data row
        $this->pdf->Cell(60, 5, number_format($trib['totTribFed'], 2, ',', '.') . ' %', 0, 0, 'L');
        $this->pdf->Cell(60, 5, number_format($trib['totTribEst'], 2, ',', '.') . ' %', 0, 0, 'L');
        $this->pdf->Cell(60, 5, number_format($trib['totTribMun'], 2, ',', '.') . ' %', 0, 1, 'L');
        $this->pdf->Ln(5);
        
        $this->pdf->SetFont('helvetica', 'B', 9);
        $this->pdf->Cell(0, 5, 'INFORMAÇÕES COMPLEMENTARES', 0, 1, 'L');
    }

    private function addTableRowWithBorders($headers, $data, $widths)
    {
        $this->pdf->SetFont('helvetica', 'B', 8);
        for ($i = 0; $i < count($headers); $i++) {
            $this->pdf->Cell($widths[$i], 5, $headers[$i], 0, 0, 'L');
        }
        $this->pdf->Ln();
        
        $this->pdf->SetFont('helvetica', '', 8);
        for ($i = 0; $i < count($data); $i++) {
            $this->pdf->Cell($widths[$i], 5, $data[$i], 0, 0, 'L');
        }
        $this->pdf->Ln();
    }

    private function formatCnpjCpf($value)
    {
        $value = preg_replace('/\D/', '', $value);
        if (strlen($value) == 14) {
            return substr($value, 0, 2) . '.' . substr($value, 2, 3) . '.' . substr($value, 5, 3) . '/' . substr($value, 8, 4) . '-' . substr($value, 12, 2);
        } elseif (strlen($value) == 11) {
            return substr($value, 0, 3) . '.' . substr($value, 3, 3) . '.' . substr($value, 6, 3) . '-' . substr($value, 9, 2);
        }
        return $value;
    }

    private function formatCep($value)
    {
        $value = preg_replace('/\D/', '', $value);
        if (strlen($value) == 8) {
            return substr($value, 0, 5) . '-' . substr($value, 5, 3);
        }
        return $value;
    }

    private function formatPhone($value)
    {
        $value = preg_replace('/\D/', '', $value);
        if (strlen($value) == 11) {
            return '(' . substr($value, 0, 2) . ') ' . substr($value, 2, 5) . '-' . substr($value, 7, 4);
        } elseif (strlen($value) == 10) {
            return '(' . substr($value, 0, 2) . ') ' . substr($value, 2, 4) . '-' . substr($value, 6, 4);
        }
        return $value;
    }

    private function formatDate($value)
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $matches)) {
            return $matches[3] . '/' . $matches[2] . '/' . $matches[1];
        }
        return $value;
    }

    private function formatDateTime($value)
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})/', $value, $matches)) {
            return $matches[3] . '/' . $matches[2] . '/' . $matches[1] . ' ' . $matches[4] . ':' . $matches[5] . ':' . $matches[6];
        }
        return $value;
    }

    private function formatCodTribNac($value)
    {
        $value = preg_replace('/\D/', '', $value);
        if (strlen($value) == 6) {
            return substr($value, 0, 2) . '.' . substr($value, 2, 2) . '.' . substr($value, 4, 2);
        }
        return $value;
    }
}

