<?php

namespace NfsePdf;

use TCPDF;

class NfsePdfGenerator {
    private $pdf;
    private $data;
    private $margin = 5;
    private $font = 'helvetica';
    private $municipality = [
        'department' => null,
        'phone' => null,
        'email' => null,
        'image' => null,
    ];

    public function __construct(string $author = 'NFS-e System', string $creator = 'NFS-e PDF Generator', string $subject = 'Documento Auxiliar da NFS-e') {
        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

        // disables TCPDF default header/footer
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);

        $this->pdf->SetCreator($creator);
        $this->pdf->SetAuthor($author);
        $this->pdf->SetSubject($subject);

        $this->pdf->SetMargins($this->margin, $this->margin, $this->margin);
        $this->pdf->SetAutoPageBreak(true, $this->margin);
        $this->pdf->SetFont($this->font, '', 8);
    }

    public function setTitle(string $title) {
        $this->pdf->SetTitle($title);

        return $this;
    }

    public function setFont(string $family = 'helvetica', string $style = '', int $size = 8) {
        $this->pdf->SetFont($family, $style, $size);
        $this->font = $family;

        return $this;
    }

    public function setMunicipality(array $dados) {
        $this->municipality = array_merge($this->municipality, $dados);

        return $this;
    }

    public function getTomadorCidadeIBGE(): int {
        return (int) $this->data['tomador']['ibgeMunicipio'];
    }

    public function setTomadorCidadeUF(string $cidade, string $uf) {
        $this->data['tomador']['municipio'] = $cidade;
        $this->data['tomador']['uf'] = $uf;
    }

    public function parseXml(string $xmlFile) {
        $xml = simplexml_load_file($xmlFile);
        if ($xml === false) {
            throw new \Exception('Failed to parse XML file');
        }

        $ns = $xml->getNamespaces(true);
        $infNFSe = $xml->children($ns[''])->infNFSe;
        $dps = $infNFSe->children($ns[''])->DPS->children($ns[''])->infDPS;

        // infNFSe/@Id → Chave de acesso da NFS-e
        // Extract Id attribute using attributes() method
        // Ex: NFS4321709...
        $id = (string) $infNFSe->attributes()->Id;

        // Chave de acesso is the Id value without "NFS" prefix for display
        $chaveAcesso = preg_replace('/^NFS/', '', $id);

        $data = [
            'chaveAcesso' => $chaveAcesso,
            'numeroNfse' => (string) $infNFSe->nNFSe,
            'localEmissao' => (string) $infNFSe->xLocEmi,
            'localPrestacao' => (string) $infNFSe->xLocPrestacao,
            'localIncidencia' => (string) $infNFSe->xLocIncid,
            'tribNac' => (string) $infNFSe->xTribNac,
            'dataProcessamento' => $this->formatDateTime((string) $infNFSe->dhProc),
            'numeroDFSe' => (string) $infNFSe->nDFSe,
            'emitente' => [
                'cnpj' => $this->formatCnpjCpf((string) $infNFSe->emit->CNPJ),
                'inscricaoMunicipal' => ((string) $infNFSe->emit->IM) ?: '-',
                'nome' => (string) $infNFSe->emit->xNome,
                'email' => (string) $infNFSe->emit->email,
                'fone' => $this->formatPhone((string) $infNFSe->emit->fone),
                'logradouro' => (string) $infNFSe->emit->enderNac->xLgr,
                'numero' => (string) $infNFSe->emit->enderNac->nro,
                'bairro' => (string) $infNFSe->emit->enderNac->xBairro,
                'municipio' => (string) $infNFSe->emit->enderNac->cMun,
                'uf' => (string) $infNFSe->emit->enderNac->UF,
                'cep' => $this->formatCep((string) $infNFSe->emit->enderNac->CEP),
                'optanteSimplesNacional' => (string) $dps->prest->regTrib->opSimpNac,
                'regimeApuracaoTributariaSN' => (string) $dps->prest->regTrib->regApTribSN,
            ],
            'tomador' => [
                'doc' => $this->formatCnpjCpf(!empty((string) $dps->toma->CPF) ? (string) $dps->toma->CPF : (string) $dps->toma->CNPJ),
                'inscricaoMunicipal' => ((string) $dps->toma->IM) ?: '-',
                'nome' => (string) $dps->toma->xNome,
                'email' => (string) $dps->toma->email,
                'fone' => $this->formatPhone((string) $dps->toma->fone),
                'logradouro' => (string) $dps->toma->end->xLgr,
                'numero' => (string) $dps->toma->end->nro,
                'complemento' => (string) $dps->toma->end->xCpl,
                'bairro' => (string) $dps->toma->end->xBairro,
                'ibgeMunicipio' => (string) $dps->toma->end->endNac->cMun,
                'cep' => $this->formatCep((string) $dps->toma->end->endNac->CEP),
            ],
            'servico' => [
                'codTribNac' => (string) $dps->serv->cServ->cTribNac,
                'codTribMun' => ((string) $dps->serv->cServ->cTribMun) ?: '-',
                'descricao' => (string) $dps->serv->cServ->xDescServ,
                'nbs' => (string) $dps->serv->cServ->cNBS,
                'infoComp' => (string) $dps->serv->infoCompl->xInfComp,
            ],
            'valores' => [
                'servico' => (float) $dps->valores->vServPrest->vServ,
                'descontoIncondicionado' => $dps->valores->vDescCondIncond->vDescIncond ?? '-',
                'totalDeducaoReducao' => $infNFSe->valores->vCalcDR ?? '-',
                'calculoBeneficioMunicipal' => $infNFSe->valores->vCalcBM ?? '-',
                'baseCalculoISSQN' => $infNFSe->valores->vBC ?? '-',
                'ISSQN' => $infNFSe->valores->vISSQN ?? '-',
                'IRRF' => $dps->valores->trib->tribFed->vRetIRRF ?? '-',
                'CP' => $dps->valores->trib->tribFed->vRetCP ?? '-',
                'CSLL' => $dps->valores->trib->tribFed->vRetCSLL ?? '-',
                'PIS' => $dps->valores->trib->tribFed->piscofins->vPis ?? '-',
                'COFINS' => $dps->valores->trib->tribFed->piscofins->vCofins ?? '-',
                'liquido' => (float) $infNFSe->valores->vLiq,
                'totalTributosFederais' => (float) $dps->valores->trib->totTrib->vTotTrib->vTotTribFed,
                'totalTributosEstaduais' => (float) $dps->valores->trib->totTrib->vTotTrib->vTotTribEst,
                'totalTributosMunicipais' => (float) $dps->valores->trib->totTrib->vTotTrib->vTotTribMun,
            ],
            'dps' => [
                'numero' => (string) $dps->nDPS,
                'serie' => (string) $dps->serie,
                'competencia' => $this->formatDate((string) $dps->dCompet),
                'dataEmissao' => $this->formatDateTime((string) $dps->dhEmi),
            ],
            'tributacao' => [
                'tipoTributacaoISSQN' => (string) $dps->valores->trib->tribMun->tribISSQN,
                'regimeEspecialTributacao' => (string) $dps->prest->regTrib->regEspTrib,
                'tipoImunidade' => (string) $dps->valores->trib->tribMun->tpImunidade ?? '-',
                'tipoSuspensao' => (string) $dps->valores->trib->tribMun->exigSusp->tpSusp ?? '-',
                'nProcessoSuspensao' => (string) $dps->valores->trib->tribMun->exigSusp->nProcesso ?? '-',
                'nBeneficioMunicipal' => (string) $dps->valores->trib->tribMun->BM->nBM ?? '-',
                'percentualAliquotaAplicadaISSQN' => (float) $infNFSe->valores->pAliqAplic ?? '-',
                'tipoRetencaoISSQN' => (string) $dps->valores->trib->tribMun->tpRetISSQN,
                'tipoRetencaoPisCofins' => (string) ($dps->valores->trib->tribFed->piscofins->tpRetPisCofins ?? '-'),
            ],
        ];


        $data['valores']['PisCofinsRetidos'] = 0;

        // 1 - PIS/COFINS Retido OU 3 - PIS Retido/COFINS Não Retido;
        if (in_array($data['tributacao']['tipoRetencaoPisCofins'], [1, 3])) {
            $data['valores']['PisCofinsRetidos'] += (float) $data['valores']['PIS'];
        }

        // 1 - PIS/COFINS Retido OU 4 - PIS Não Retido/COFINS Retido;
        if (in_array($data['tributacao']['tipoRetencaoPisCofins'], [1, 4])) {
            $data['valores']['PisCofinsRetidos'] += (float) $data['valores']['COFINS'];
        }

        $data['valores']['IrrfCpCsllRetidos'] = (float) $data['valores']['IRRF'] + (float) $data['valores']['CP'] + (float) $data['valores']['CSLL'];

        $data['valores']['servico'] = $this->money($data['valores']['servico']);
        $data['valores']['descontoIncondicionado'] = $this->money($data['valores']['descontoIncondicionado']);
        $data['valores']['totalDeducaoReducao'] = $this->money($data['valores']['totalDeducaoReducao']);
        $data['valores']['calculoBeneficioMunicipal'] = $this->money($data['valores']['calculoBeneficioMunicipal']);
        $data['valores']['baseCalculoISSQN'] = $this->money($data['valores']['baseCalculoISSQN']);
        $data['valores']['ISSQN'] = $this->money($data['valores']['ISSQN']);
        $data['valores']['IRRF'] = $this->money($data['valores']['IRRF']);
        $data['valores']['CP'] = $this->money($data['valores']['CP']);
        $data['valores']['CSLL'] = $this->money($data['valores']['CSLL']);
        $data['valores']['PIS'] = $this->money($data['valores']['PIS']);
        $data['valores']['COFINS'] = $this->money($data['valores']['COFINS']);
        $data['valores']['IrrfCpCsllRetidos'] = $this->money($data['valores']['IrrfCpCsllRetidos']);
        $data['valores']['PisCofinsRetidos'] = $this->money($data['valores']['PisCofinsRetidos']);
        $data['valores']['liquido'] = $this->money($data['valores']['liquido']);
        $data['valores']['totalTributosFederais'] = $this->money($data['valores']['totalTributosFederais']);
        $data['valores']['totalTributosEstaduais'] = $this->money($data['valores']['totalTributosEstaduais']);
        $data['valores']['totalTributosMunicipais'] = $this->money($data['valores']['totalTributosMunicipais']);

        $data['tributacao']['percentualAliquotaAplicadaISSQN'] = $this->money($data['tributacao']['percentualAliquotaAplicadaISSQN'], 2, true);

        $this->data = $data;

        return $this;
    }

    /**
     * Gera o PDF do DANFSe (Documento Auxiliar da NFS-e Nacional).
     *
     * Este método apenas organiza a renderização.
     * Os dados devem ser previamente carregados via parseXml().
     *
     * @return TCPDF
     */
    public function generate() {
        $this->pdf->AddPage();

        $this->addHeader();
        $this->addHorizontalLine();
        $this->addDadosNfse();
        $this->addHorizontalLine();
        $this->addEmitente();
        $this->addHorizontalLine();
        $this->addTomador();
        $this->addHorizontalLine();
        $this->addIntermediario();
        $this->addHorizontalLine();
        $this->addServico();
        $this->addHorizontalLine();
        $this->addTributacaoMunicipal();
        $this->addHorizontalLine();
        $this->addTributacaoFederal();
        $this->addHorizontalLine();
        $this->addValores();
        $this->addHorizontalLine();
        $this->addTotaisTributos();
        $this->addHorizontalLine();
        $this->addInformacoesComplementares();


        // Draw border around the entire document after all content is added
        // This ensures it encompasses everything including "INFORMAÇÕES COMPLEMENTARES"
        $this->drawDocumentBorder();

        return $this->pdf;
    }

    private function drawDocumentBorder() {
        // Draw a rectangle border around the entire document
        // Using absolute coordinates from page top-left corner
        $pageWidth = 210; // A4 width in mm
        $pageHeight = 297; // A4 height in mm

        $x1 = $this->margin - 3;
        $y1 = $this->margin - 3;
        $width = $pageWidth - (2 * $this->margin - 5);  // Total width minus both margins
        $height = $pageHeight - (2 * $this->margin - 5); // Total height minus both margins

        // Set line width for border
        $this->pdf->SetLineWidth(0.1);

        // Draw rectangle border using absolute coordinates from page top
        // Rect(x, y, width, height, style)
        $this->pdf->Rect($x1, $y1, $width, $height, 'D');
    }

    private function addHorizontalLine() {
        $y = $this->pdf->GetY();
        $pageWidth = 210; // A4 width in mm
        $rightEdge = $pageWidth - $this->margin;
        $this->pdf->Line($this->margin, $y, $rightEdge, $y);
        $this->pdf->Ln(2);
    }

    private function addHeader() {
        $startY = $this->pdf->GetY();
        $col4X = 147;

        // Left column - Logo image
        $logoPath = __DIR__ . '/../assets/logo-nfse-assinatura-horizontal.png';
        if (file_exists($logoPath)) {
            $this->pdf->Image($logoPath, $this->margin, $startY, 50, 0, 'PNG', '', '', false, 300, '', false, false, 0, false, false, false);
        }

        // Center column - Main title 
        $centerX = 62;
        $this->pdf->SetXY($centerX, $startY);
        $this->pdf->SetFont($this->font, 'B', 9);
        $this->pdf->Cell(50, 4, 'DANFSe v1.0', 0, 0, 'C');
        $this->pdf->SetXY($centerX, $startY + 4);
        $this->pdf->SetFont($this->font, 'B', 9);
        $this->pdf->Cell(50, 4, 'Documento Auxiliar da NFS-e', 0, 0, 'C');

        // Right column - Municipality info
        $imageX = $col4X - 15;

        // Municipality logo (coat of arms)
        if (!empty($this->municipality['image']) && file_exists($this->municipality['image'])) {
            $maxW = 14;
            $maxH = 11;

            [$w, $h] = $this->getImageSizes($this->municipality['image'], $maxW, $maxH);

            // Centraliza dentro do retângulo
            $x = $imageX + ($maxW - $w) / 2;
            $y = $startY + ($maxH - $h) / 2;

            $this->pdf->Image($this->municipality['image'], $x, $y, $w, $h);
        }

        $rowMunicipalityY = $startY;
        $this->pdf->SetXY($col4X, $startY);
        $this->pdf->SetFont($this->font, 'B', 8);
        $this->pdf->Cell(57, 3, 'Prefeitura Municipal de ' . $this->data['localEmissao'], 0, 1, 'L');
        $this->pdf->SetXY($col4X, $rowMunicipalityY += 3);
        $this->pdf->SetFont($this->font, '', 6);

        if (!empty($this->municipality['department'])) {
            $this->pdf->Cell(57, 2.5, $this->municipality['department'], 0, 1, 'L');
            $this->pdf->SetXY($col4X, $rowMunicipalityY += 2.5);
        }

        if (!empty($this->municipality['phone'])) {
            $this->pdf->Cell(57, 2.5, $this->municipality['phone'], 0, 1, 'L');
            $this->pdf->SetXY($col4X, $rowMunicipalityY += 2.5);
        }

        if (!empty($this->municipality['email'])) {
            $this->pdf->Cell(57, 2.5, $this->municipality['email'], 0, 1, 'L');
        }

        // Move Y position down for next content
        $this->pdf->SetY($startY + 12);
        $this->pdf->Ln(1);
    }

    private function addDadosNfse() {
        $col1X = $this->margin;
        $col2X = 47;
        $col3X = 97;
        $col4X = 147;
        $col1W = 45;
        $col2W = 50;
        $col3W = 50;
        $col4W = 50;

        $startY = $this->pdf->GetY();

        // Chave de Acesso row - spans all columns
        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->SetXY($col1X, $startY);
        $this->pdf->Cell($col1W + $col2W + $col3W + $col4W, 4, 'Chave de Acesso da NFS-e', 0, 1, 'L');
        $this->pdf->SetFont($this->font, '', 8);
        $this->pdf->SetXY($col1X, $startY + 4);
        $this->pdf->Cell($col1W + $col2W + $col3W + $col4W, 4, $this->data['chaveAcesso'], 0, 1, 'L');

        // First row - NFS-e headers
        $row1Y = $this->pdf->GetY();

        // QR Code positioned FIRST in 4th column (centered, larger, above all text)
        $qrUrl = 'https://www.nfse.gov.br/ConsultaPublica?tpc=1&chave=' . $this->data['chaveAcesso'];

        // Position QR code higher above row1Y to avoid overlapping with text
        $qrY = $row1Y - 10;

        // Center the QR code horizontally in the 4th column
        $qrSize = 18; // QR code size
        $qrX = $col4X + $col4W / 2 - $qrSize / 2;

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

        // Now draw the text in columns 1-3
        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->SetXY($col1X, $row1Y);
        $this->pdf->Cell($col1W, 4, 'Número da NFS-e', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row1Y);
        $this->pdf->Cell($col2W, 4, 'Competência da NFS-e', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row1Y);
        $this->pdf->Cell($col3W, 4, 'Data e Hora da emissão da NFS-e', 0, 0, 'L');

        // Second row - NFS-e data
        $this->pdf->SetFont($this->font, '', 8);
        $row2Y = $row1Y + 4;
        $this->pdf->SetXY($col1X, $row2Y);
        $this->pdf->Cell($col1W, 4, $this->data['numeroNfse'], 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row2Y);
        $this->pdf->Cell($col2W, 4, $this->data['dps']['competencia'], 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row2Y);
        $this->pdf->Cell($col3W, 4, $this->data['dataProcessamento'], 0, 0, 'L');

        // Empty cell for row 2, column 4 (QR code occupies this space)
        $this->pdf->SetXY($col4X, $row2Y);
        $this->pdf->Cell($col4W, 4, '', 0, 1, 'L');

        // Third row - DPS headers (4th column empty)
        $row3Y = $this->pdf->GetY();
        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->SetXY($col1X, $row3Y);
        $this->pdf->Cell($col1W, 4, 'Número da DPS', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row3Y);
        $this->pdf->Cell($col2W, 4, 'Série da DPS', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row3Y);
        $this->pdf->Cell($col3W, 4, 'Data e Hora da emissão da DPS', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row3Y);
        $this->pdf->Cell($col4W, 4, '', 0, 1, 'L');

        // Fourth row - DPS data (authenticity message in 4th column, below QR code)
        $this->pdf->SetFont($this->font, '', 8);
        $row4Y = $row3Y + 4;
        $this->pdf->SetXY($col1X, $row4Y);
        $this->pdf->Cell($col1W, 4, $this->data['dps']['numero'], 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row4Y);
        $this->pdf->Cell($col2W, 4, $this->data['dps']['serie'], 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row4Y);
        $this->pdf->Cell($col3W, 4, $this->data['dps']['dataEmissao'], 0, 0, 'L');

        // Authenticity message positioned in 4th column, below QR code
        $this->pdf->SetXY($col4X, $row4Y);
        $this->pdf->SetFont($this->font, '', 6);
        $message = 'A autenticidade desta NFS-e pode ser verificada pela leitura deste código QR ou pela consulta da chave de acesso no portal nacional da NFS-e';
        $this->pdf->MultiCell($col4W - 1, 1, $message, 0, 'L', false, 1, $col4X, $row4Y - 4);

        $this->pdf->Ln(2);
    }

    private function addEmitente() {
        $col1X = $this->margin;
        $col2X = 47;
        $col3X = 97;
        $col4X = 147;
        $col1W = 45;
        $col2W = 50;
        $col3W = 50;
        $col4W = 50;

        $emit = $this->data['emitente'];
        $startY = $this->pdf->GetY();

        // Header row
        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->SetXY($col1X, $startY);
        $this->pdf->Cell($col1W, 4, 'EMITENTE DA NFS-e', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $startY);
        $this->pdf->Cell($col2W, 4, 'CNPJ / CPF / NIF', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $startY);
        $this->pdf->Cell($col3W, 4, 'Inscrição Municipal', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $startY);
        $this->pdf->Cell($col4W, 4, 'Telefone', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 8);
        $this->pdf->SetXY($col1X, $startY + 4);
        $this->pdf->Cell($col1W, 4, 'Prestador do Serviço', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $startY + 4);
        $this->pdf->Cell($col2W, 4, $emit['cnpj'], 0, 0, 'L');
        $this->pdf->SetXY($col3X, $startY + 4);
        $this->pdf->Cell($col3W, 4, $emit['inscricaoMunicipal'] ?? '-', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $startY + 4);
        $this->pdf->Cell($col4W, 4, $emit['fone'], 0, 1, 'L');

        // Header row
        $row2Y = $this->pdf->GetY();
        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->SetXY($col1X, $row2Y);
        $this->pdf->Cell($col1W, 4, 'Nome / Nome Empresarial', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row2Y);
        $this->pdf->Cell($col2W, 4, '', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row2Y);
        $this->pdf->Cell($col3W, 4, 'E-mail', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row2Y);
        $this->pdf->Cell($col4W, 4, '', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 8);
        $this->pdf->SetXY($col1X, $row2Y + 4);
        $this->pdf->MultiCell($col1W + $col2W, 4, $emit['nome'], 0, 'L');

        $row3Y = max($this->pdf->GetY(), $row2Y + 4); // altura abaixo do multicell, que pode quebrar de linha

        $this->pdf->SetXY($col3X, $row2Y + 4);
        $this->pdf->Cell($col3W, 4, $emit['email'], 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row2Y + 4);
        $this->pdf->Cell($col4W, 4, '', 0, 1, 'L');

        // Header row
        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->SetXY($col1X, $row3Y);
        $this->pdf->Cell($col1W, 4, 'Endereço', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row3Y);
        $this->pdf->Cell($col2W, 4, '', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row3Y);
        $this->pdf->Cell($col3W, 4, 'Município', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row3Y);
        $this->pdf->Cell($col4W, 4, 'CEP', 0, 1, 'L');

        $endereco = $emit['logradouro'] . ', ' . $emit['numero'] . ', ' . $emit['bairro'];

        // Data row
        $this->pdf->SetFont($this->font, '', 8);
        $this->pdf->SetXY($col1X, $row3Y + 4);
        $this->pdf->MultiCell($col1W + $col2W, 4, $endereco, 0, 'L');

        $row4Y = max($this->pdf->GetY(), $row3Y + 4); // altura abaixo do multicell, que pode quebrar de linha

        $this->pdf->SetXY($col3X, $row3Y + 4);
        $this->pdf->Cell($col3W, 4, $this->data['localEmissao'] . ' - ' . $emit['uf'], 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row3Y + 4);
        $this->pdf->Cell($col4W, 4, $emit['cep'], 0, 1, 'L');

        // Header row
        $this->pdf->SetXY($col1X, $row4Y);
        $this->pdf->setFont($this->font, 'B', 8);
        $this->pdf->Cell($col1W, 4, 'Simples Nacional na Data de Competência', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $this->pdf->GetY());
        $this->pdf->Cell($col3W + $col4W, 4, 'Regime de Apuração Tributária pelo SN', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 8);
        $this->pdf->SetXY($col1X, $this->pdf->GetY());
        $this->pdf->Cell($col1W, 4, $this->optanteSimplesNacional($emit['optanteSimplesNacional']), 0, 0, 'L');
        $this->pdf->SetXY($col3X, $this->pdf->GetY());
        $this->pdf->MultiCell($col3W + $col4W, 4, $this->regimeApuracaoTributariaSN($emit['regimeApuracaoTributariaSN']), 0, 'L');
        $this->pdf->Ln(2);
    }

    private function addTomador() {
        $col1X = $this->margin;
        $col2X = 47;
        $col3X = 97;
        $col4X = 147;
        $col1W = 45;
        $col2W = 50;
        $col3W = 50;
        $col4W = 50;

        $toma = $this->data['tomador'];
        $startY = $this->pdf->GetY();

        // Header row
        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->SetXY($col1X, $startY);
        $this->pdf->Cell($col1W, 4, 'TOMADOR DO SERVIÇO', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $startY);
        $this->pdf->Cell($col2W, 4, 'CNPJ / CPF / NIF', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $startY);
        $this->pdf->Cell($col3W, 4, 'Inscrição Municipal', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $startY);
        $this->pdf->Cell($col4W, 4, 'Telefone', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 8);
        $this->pdf->SetXY($col1X, $startY + 4);
        $this->pdf->Cell($col1W, 4, '', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $startY + 4);
        $this->pdf->Cell($col2W, 4, $toma['doc'], 0, 0, 'L');
        $this->pdf->SetXY($col3X, $startY + 4);
        $this->pdf->Cell($col3W, 4, $toma['inscricaoMunicipal'], 0, 0, 'L');
        $this->pdf->SetXY($col4X, $startY + 4);
        $this->pdf->Cell($col4W, 4, $toma['fone'], 0, 1, 'L');

        // Header row
        $row2Y = $this->pdf->GetY();
        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->SetXY($col1X, $row2Y);
        $this->pdf->Cell($col1W, 4, 'Nome / Nome Empresarial', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row2Y);
        $this->pdf->Cell($col2W, 4, '', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row2Y);
        $this->pdf->Cell($col3W, 4, 'E-mail', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row2Y);
        $this->pdf->Cell($col4W, 4, '', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 8);
        $this->pdf->SetXY($col1X, $row2Y + 4);
        $this->pdf->MultiCell($col1W + $col2W, 4, $toma['nome'], 0, 'L');

        $row3Y = max($this->pdf->GetY(), $row2Y + 4); // altura abaixo do multicell, que pode quebrar de linha

        $this->pdf->SetXY($col3X, $row2Y + 4);
        $this->pdf->Cell($col3W + $col4W, 4, $toma['email'], 0, 0, 'L');

        // Header row
        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->SetXY($col1X, $row3Y);
        $this->pdf->Cell($col1W, 4, 'Endereço', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row3Y);
        $this->pdf->Cell($col2W, 4, '', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row3Y);
        $this->pdf->Cell($col3W, 4, 'Município', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row3Y);
        $this->pdf->Cell($col4W, 4, 'CEP', 0, 1, 'L');

        [$tomadorCidade, $tomadorEndereco] = $this->enderecoTomador($toma);

        // Data row
        $this->pdf->SetFont($this->font, '', 8);
        $this->pdf->SetXY($col1X, $row3Y + 4);
        $this->pdf->MultiCell($col1W + $col2W, 4, $tomadorEndereco, 0, 'L');

        $row4Y = max($this->pdf->GetY(), $row2Y + 4); // altura abaixo do multicell, que pode quebrar de linha

        $this->pdf->SetXY($col3X, $row3Y + 4);
        $this->pdf->Cell($col3W, 4, $tomadorCidade, 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row3Y + 4);
        $this->pdf->Cell($col4W, 4, $toma['cep'], 0, 1, 'L');

        $this->pdf->setY($row4Y); // após o final do endereco
        $this->pdf->Ln(2);
    }

    private function addIntermediario() {
        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->Cell(0, 0, 'INTERMEDIÁRIO DO SERVIÇO NÃO IDENTIFICADO NA NFS-e', 0, 1, 'C');

        $this->pdf->Ln(2);
    }

    private function addServico() {
        $col1X = $this->margin;
        $col2X = 47;
        $col3X = 97;
        $col4X = 147;
        $col1W = 45;
        $col2W = 50;
        $col3W = 50;
        $col4W = 50;

        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->Cell(0, 4, 'SERVIÇO PRESTADO', 0, 1, 'L');
        $this->pdf->SetFont($this->font, '', 8);

        $startY = $this->pdf->GetY();

        // Header row
        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->SetXY($col1X, $startY);
        $this->pdf->Cell($col1W, 4, 'Código de Tributação Nacional', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $startY);
        $this->pdf->Cell($col2W, 4, 'Código de Tributação Municipal', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $startY);
        $this->pdf->Cell($col3W, 4, 'Local da Prestação', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $startY);
        $this->pdf->Cell($col4W, 4, 'País da Prestação', 0, 1, 'L');

        $serv = $this->data['servico'];

        $codTribNac = $this->formatCodTribNac($serv['codTribNac']) . ' - ' . $this->data['tribNac'];
        $codTribNac = $this->truncateTextToLines($codTribNac, $col1W - 2, 2);

        // Data row - Format code as 01.03.02
        $this->pdf->SetFont($this->font, '', 8);
        $this->pdf->SetXY($col1X, $startY + 4);
        $this->pdf->MultiCell($col1W, 4, $codTribNac, 0, 'L');

        $row2Y = max($this->pdf->GetY(), $startY + 4); // altura abaixo do multicell, que pode quebrar de linha

        $this->pdf->SetXY($col2X, $startY + 4);
        $this->pdf->Cell($col2W, 4, $serv['codTribMun'], 0, 0, 'L');
        $this->pdf->SetXY($col3X, $startY + 4);
        $this->pdf->Cell($col3W, 4, $this->data['localPrestacao'], 0, 0, 'L');
        $this->pdf->SetXY($col4X, $startY + 4);
        $this->pdf->Cell($col4W, 4, '-', 0, 1, 'L');

        // Descrição
        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->SetXY($col1X, $row2Y);
        $this->pdf->Cell($col1W, 4, 'Descrição do Serviço', 0, 0, 'L');
        $this->pdf->SetFont($this->font, '', 8);
        $this->pdf->SetXY($col2X, $row2Y);
        $this->pdf->MultiCell($col2W + $col3W + $col4W, 4, $serv['descricao'], 0, 'L');
        $this->pdf->Ln(2);
    }

    private function addTributacaoMunicipal() {
        $col1X = $this->margin; // Adjusted for 2mm margin (was 10)
        $col2X = 47; // Adjusted for 2mm margin (was 55, now 55-8=47)
        $col3X = 97; // Adjusted for 2mm margin (was 105, now 105-8=97)
        $col4X = 147; // Adjusted for 2mm margin (was 155, now 155-8=147)
        $col1W = 45;
        $col2W = 50;
        $col3W = 50;
        $col4W = 50;

        $trib = $this->data['tributacao'];
        $val = $this->data['valores'];

        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->Cell(0, 4, 'TRIBUTAÇÃO MUNICIPAL', 0, 1, 'L');
        $this->pdf->SetFont($this->font, '', 8);

        $startY = $this->pdf->GetY();

        // Header row
        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->SetXY($col1X, $startY);
        $this->pdf->Cell($col1W, 4, 'Tributação do ISSQN', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $startY);
        $this->pdf->Cell($col2W, 4, 'País Resultado da Prestação do Serviço', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $startY);
        $this->pdf->Cell($col3W, 4, 'Município de Incidência do ISSQN', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $startY);
        $this->pdf->Cell($col4W, 4, 'Regime Especial de Tributação', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 8);
        $this->pdf->SetXY($col1X, $startY + 4);
        $this->pdf->Cell($col1W, 4, $this->tributacaoISSQN($trib['tipoTributacaoISSQN']), 0, 0, 'L');
        $this->pdf->SetXY($col2X, $startY + 4);
        $this->pdf->Cell($col2W, 4, '-', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $startY + 4);
        $this->pdf->Cell($col3W, 4, $this->data['localIncidencia'], 0, 0, 'L');
        $this->pdf->SetXY($col4X, $startY + 4);
        $this->pdf->Cell($col4W, 4, $this->regimeEspecialTributacao($trib['regimeEspecialTributacao']), 0, 1, 'L');

        // Header row
        $row2Y = $this->pdf->GetY();
        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->SetXY($col1X, $row2Y);
        $this->pdf->Cell($col1W, 4, 'Tipo de Imunidade', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row2Y);
        $this->pdf->Cell($col2W, 4, 'Suspensão da Exigibilidade do ISSQN', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row2Y);
        $this->pdf->Cell($col3W, 4, 'Número Processo Suspensão', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row2Y);
        $this->pdf->Cell($col4W, 4, 'Benefício Municipal', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 8);
        $this->pdf->SetXY($col1X, $row2Y + 4);
        $this->pdf->Cell($col1W, 4, $this->tipoImunidade($trib['tipoImunidade']), 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row2Y + 4);
        $this->pdf->Cell($col2W, 4, $this->suspensaoExigibilidadeISSQN($trib['tipoSuspensao']), 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row2Y + 4);
        $this->pdf->Cell($col3W, 4, $trib['nProcessoSuspensao'] ?: '-', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row2Y + 4);
        $this->pdf->Cell($col4W, 4, $trib['nBeneficioMunicipal'] ?: '-', 0, 1, 'L');

        // Header row
        $row3Y = $this->pdf->GetY();
        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->SetXY($col1X, $row3Y);
        $this->pdf->Cell($col1W, 4, 'Valor do Serviço', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row3Y);
        $this->pdf->Cell($col2W, 4, 'Desconto Incondicionado', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row3Y);
        $this->pdf->Cell($col3W, 4, 'Total Deduções/Reduções', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row3Y);
        $this->pdf->Cell($col4W, 4, 'Cálculo do BM', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 8);
        $this->pdf->SetXY($col1X, $row3Y + 4);
        $this->pdf->Cell($col1W, 4, $val['servico'], 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row3Y + 4);
        $this->pdf->Cell($col2W, 4, $val['descontoIncondicionado'], 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row3Y + 4);
        $this->pdf->Cell($col3W, 4, $val['totalDeducaoReducao'], 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row3Y + 4);
        $this->pdf->Cell($col4W, 4, $val['calculoBeneficioMunicipal'], 0, 1, 'L');

        // Header row
        $row4Y = $this->pdf->GetY();
        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->SetXY($col1X, $row4Y);
        $this->pdf->Cell($col1W, 4, 'BC ISSQN', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row4Y);
        $this->pdf->Cell($col2W, 4, 'Alíquota Aplicada', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row4Y);
        $this->pdf->Cell($col3W, 4, 'Retenção do ISSQN', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row4Y);
        $this->pdf->Cell($col4W, 4, 'ISSQN Apurado', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 8);
        $this->pdf->SetXY($col1X, $row4Y + 4);
        $this->pdf->Cell($col1W, 4, $val['baseCalculoISSQN'], 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row4Y + 4);
        $this->pdf->Cell($col2W, 4, $trib['percentualAliquotaAplicadaISSQN'], 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row4Y + 4);
        $this->pdf->Cell($col3W, 4, $this->tipoRetencaoISSQN($trib['tipoRetencaoISSQN']), 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row4Y + 4);
        $this->pdf->Cell($col4W, 4, $val['ISSQN'], 0, 1, 'L');
        $this->pdf->Ln(2);
    }

    private function addTributacaoFederal() {
        $col1X = $this->margin; // Adjusted for 2mm margin (was 10)
        $col2X = 47; // Adjusted for 2mm margin (was 55, now 55-8=47)
        $col3X = 97; // Adjusted for 2mm margin (was 105, now 105-8=97)
        $col4X = 147; // Adjusted for 2mm margin (was 155, now 155-8=147)
        $col1W = 45;
        $col2W = 50;
        $col3W = 50;
        $col4W = 50;

        $val = $this->data['valores'];
        $trib = $this->data['tributacao'];

        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->Cell(0, 4, 'TRIBUTAÇÃO FEDERAL', 0, 1, 'L');
        $this->pdf->SetFont($this->font, '', 8);

        // Header row
        $row5Y = $this->pdf->GetY();
        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->SetXY($col1X, $row5Y);
        $this->pdf->Cell($col1W, 4, 'IRRF', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row5Y);
        $this->pdf->Cell($col2W, 4, 'CP', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row5Y);
        $this->pdf->Cell($col3W, 4, 'CSLL', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row5Y);
        $this->pdf->Cell($col4W, 4, '', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 8);
        $this->pdf->SetXY($col1X, $row5Y + 4);
        $this->pdf->Cell($col1W, 4, $val['IRRF'], 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row5Y + 4);
        $this->pdf->Cell($col2W, 4, $val['CP'], 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row5Y + 4);
        $this->pdf->Cell($col3W, 4, $val['CSLL'], 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row5Y + 4);
        $this->pdf->Cell($col4W, 4, '', 0, 1, 'L');

        // Header row
        $row6Y = $this->pdf->GetY();
        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->SetXY($col1X, $row6Y);
        $this->pdf->Cell($col1W, 4, 'PIS', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row6Y);
        $this->pdf->Cell($col2W, 4, 'COFINS', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row6Y);
        $this->pdf->Cell($col3W, 4, 'Retenção do PIS/COFINS', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row6Y);
        $this->pdf->Cell($col4W, 4, 'TOTAL TRIBUTAÇÃO FEDERAL', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 8);
        $this->pdf->SetXY($col1X, $row6Y + 4);
        $this->pdf->Cell($col1W, 4, $val['PIS'], 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row6Y + 4);
        $this->pdf->Cell($col2W, 4, $val['COFINS'], 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row6Y + 4);
        $this->pdf->Cell($col3W, 4, $this->retencaoPisCofins($trib['tipoRetencaoPisCofins']), 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row6Y + 4);
        $this->pdf->Cell($col4W, 4, $val['totalTributosFederais'], 0, 1, 'L');
        $this->pdf->Ln(2);
    }

    private function addValores() {
        $col1X = $this->margin; // Adjusted for 2mm margin (was 10)
        $col2X = 47; // Adjusted for 2mm margin (was 55, now 55-8=47)
        $col3X = 97; // Adjusted for 2mm margin (was 105, now 105-8=97)
        $col4X = 147; // Adjusted for 2mm margin (was 155, now 155-8=147)
        $col1W = 45;
        $col2W = 50;
        $col3W = 50;
        $col4W = 50;

        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->Cell(0, 4, 'VALOR TOTAL DA NFS-E', 0, 1, 'L');
        $this->pdf->SetFont($this->font, '', 8);

        $val = $this->data['valores'];
        $startY = $this->pdf->GetY();

        // Header row
        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->SetXY($col1X, $startY);
        $this->pdf->Cell($col1W, 4, 'Valor do Serviço', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $startY);
        $this->pdf->Cell($col2W, 4, 'Desconto Condicionado', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $startY);
        $this->pdf->Cell($col3W, 4, 'Desconto Incondicionado', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $startY);
        $this->pdf->Cell($col4W, 4, 'ISSQN Retido', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 8);
        $this->pdf->SetXY($col1X, $startY + 4);
        $this->pdf->Cell($col1W, 4, $val['servico'], 0, 0, 'L');
        $this->pdf->SetXY($col2X, $startY + 4);
        $this->pdf->Cell($col2W, 4, '-', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $startY + 4);
        $this->pdf->Cell($col3W, 4, '-', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $startY + 4);
        $this->pdf->Cell($col4W, 4, '-', 0, 1, 'L');

        // Header row
        $row2Y = $this->pdf->GetY();
        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->SetXY($col1X, $row2Y);
        $this->pdf->Cell($col1W, 4, 'IRRF, CP, CSLL - Retidos', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row2Y);
        $this->pdf->Cell($col2W, 4, 'PIS/COFINS Retidos', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row2Y);
        $this->pdf->Cell($col3W, 4, '', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row2Y);
        $this->pdf->Cell($col4W, 4, 'Valor Líquido da NFS-e', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 8);
        $this->pdf->SetXY($col1X, $row2Y + 4);
        $this->pdf->Cell($col1W, 4, $val['IrrfCpCsllRetidos'], 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row2Y + 4);
        $this->pdf->Cell($col2W, 4, $val['PisCofinsRetidos'], 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row2Y + 4);
        $this->pdf->Cell($col3W, 4, '', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row2Y + 4);
        $this->pdf->Cell($col4W, 4, $val['liquido'], 0, 1, 'L');
        $this->pdf->Ln(2);
    }

    private function addTotaisTributos() {
        $colW = 65;
        $col1X = $this->margin;
        $col2X = $col1X + $colW;
        $col3X = $col2X + $colW + 4;

        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->Cell(0, 4, 'TOTAIS APROXIMADOS DOS TRIBUTOS', 0, 1, 'L');
        $this->pdf->SetFont($this->font, '', 8);

        $val = $this->data['valores'];
        $startY = $this->pdf->GetY();

        // Header row
        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->SetXY($col1X, $startY);
        $this->pdf->Cell($colW, 4, 'Federais', 0, 0, 'C');
        $this->pdf->SetXY($col2X, $startY);
        $this->pdf->Cell($colW + 4, 4, 'Estaduais', 0, 0, 'C');
        $this->pdf->SetXY($col3X, $startY);
        $this->pdf->Cell($colW, 4, 'Municípios', 0, 1, 'C');

        // Data row
        $this->pdf->SetFont($this->font, '', 8);
        $this->pdf->SetXY($col1X, $startY + 4);
        $this->pdf->Cell($colW, 4, $val['totalTributosFederais'], 0, 0, 'C');
        $this->pdf->SetXY($col2X, $startY + 4);
        $this->pdf->Cell($colW + 4, 4, $val['totalTributosEstaduais'], 0, 0, 'C');
        $this->pdf->SetXY($col3X, $startY + 4);
        $this->pdf->Cell($colW, 4, $val['totalTributosMunicipais'], 0, 1, 'C');
        $this->pdf->Ln(2);
    }

    private function addInformacoesComplementares() {
        // Header row
        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->Cell(0, 4, 'INFORMAÇÕES COMPLEMENTARES', 0, 1, 'L');
        $this->pdf->SetFont($this->font, '', 7);

        // Data row
        if (!empty($this->data['servico']['infoComp']) || !empty($this->data['servico']['nbs'])) {
            $infoComp = $this->data['servico']['infoComp'] ?? '';
            $infoComp = $infoComp ? $infoComp . "\n" : '';
            $infoComp .= 'NBS: ' . $this->data['servico']['nbs'];

            $this->pdf->MultiCell(0, 0, $infoComp, 0, 'L');
        }
    }


    private function enderecoTomador(array $toma): array {
        $cidade = $toma['municipio'] ?? null;

        if ($cidade) {
            $cidade = "$toma[municipio] - $toma[uf]";
        } else {
            $cidade_emissao = $this->data['localEmissao'];
            $cidade_prestacao = $this->data['localPrestacao'];

            $cidade = !empty($cidade_prestacao) && $cidade_prestacao != $cidade_emissao
                ? $cidade_prestacao
                : $cidade_emissao;
        }

        $complemento = !empty($toma['complemento']) ? ", $toma[complemento]" : '';
        $endereco = "$toma[logradouro], $toma[numero]$complemento, $toma[bairro]";

        return [$cidade, $endereco];
    }

    private function formatCnpjCpf(string $value) {
        $value = preg_replace('/\D/', '', $value);

        if (strlen($value) == 14) {
            return substr($value, 0, 2) . '.' . substr($value, 2, 3) . '.' . substr($value, 5, 3) . '/' . substr($value, 8, 4) . '-' . substr($value, 12, 2);
        } elseif (strlen($value) == 11) {
            return substr($value, 0, 3) . '.' . substr($value, 3, 3) . '.' . substr($value, 6, 3) . '-' . substr($value, 9, 2);
        }

        return $value;
    }

    private function formatCep(string $value) {
        $value = preg_replace('/\D/', '', $value);

        if (strlen($value) == 8) {
            return substr($value, 0, 5) . '-' . substr($value, 5, 3);
        }

        return $value;
    }

    private function formatPhone(string $value) {
        $value = preg_replace('/\D/', '', $value);

        if (strlen($value) == 13) {
            return '+' . substr($value, 0, 2) . ' ' . substr($value, 2, 2) . ' ' . substr($value, 4, 5) . '-' . substr($value, 9, 4);
        } elseif (strlen($value) == 12) {
            return '+' . substr($value, 0, 2) . ' ' . substr($value, 2, 2) . ' ' . substr($value, 4, 4) . '-' . substr($value, 8, 4);
        } elseif (strlen($value) == 11) {
            return '(' . substr($value, 0, 2) . ') ' . substr($value, 2, 5) . '-' . substr($value, 7, 4);
        } elseif (strlen($value) == 10) {
            return '(' . substr($value, 0, 2) . ') ' . substr($value, 2, 4) . '-' . substr($value, 6, 4);
        } elseif (strlen($value) == 9) {
            return substr($value, 0, 5) . '-' . substr($value, 5, 4);
        } elseif (strlen($value) == 8) {
            return substr($value, 0, 4) . '-' . substr($value, 4, 4);
        }

        return $value ?: '-';
    }

    private function formatDate(string $value) {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $matches)) {
            return $matches[3] . '/' . $matches[2] . '/' . $matches[1];
        }

        return $value;
    }

    private function formatDateTime(string $value) {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})/', $value, $matches)) {
            return $matches[3] . '/' . $matches[2] . '/' . $matches[1] . ' ' . $matches[4] . ':' . $matches[5] . ':' . $matches[6];
        }

        return $value;
    }

    private function formatCodTribNac(string $value) {
        $value = preg_replace('/\D/', '', $value);

        if (strlen($value) == 6) {
            return substr($value, 0, 2) . '.' . substr($value, 2, 2) . '.' . substr($value, 4, 2);
        }

        return $value;
    }

    private function getImageSizes(string $imgDir, float $maxW = 0, float $maxH = 0) {
        if ($maxH + $maxH == 0) {
            die('Erro: Ao menos um dos parâmetros de tamanho máximo deve ser maior que zero.');
        }

        // Tamanho real da imagem (px)
        [$imgW, $imgH] = getimagesize($imgDir);

        // Proporções
        $ratioW = $maxW / $imgW;
        $ratioH = $maxH / $imgH;

        // Usa o menor fator (mantém proporção)
        $scale = min($ratioW, $ratioH);

        // Tamanho final
        $w = $imgW * $scale;
        $h = $imgH * $scale;

        return [$w, $h];
    }

    private function truncateTextToLines(string $text, float $width, int $maxLines) {
        $lines = $this->pdf->getNumLines($text, $width);

        if ($lines <= $maxLines) {
            return $text;
        }

        $current = '';
        $words = preg_split('/\s+/', $text);

        foreach ($words as $word) {
            $test = trim("$current $word");

            if ($this->pdf->getNumLines("$test...", $width) > $maxLines) {
                break;
            }

            $current = $test;
        }

        return rtrim($current) . '...';
    }

    private function money(string|float $val, int $decimais = 2, bool $percentual = false): string {
        if (!is_numeric($val)) {
            return $val;
        }

        return ($percentual ? '' : 'R$ ') . number_format($val, $decimais, ',', '.') . ($percentual ? ' %' : '');
    }


    // auxiliary functions
    private function optanteSimplesNacional($cOpSimpNac): string {
        // NFSe/infNFSe/DPS/infDPS/prest/regTrib/opSimpNac		
        // Situação perante Simples Nacional:
        // 1 - Não Optante;
        // 2 - Optante - Microempreendedor Individual (MEI);
        // 3 - Optante - Microempresa ou Empresa de Pequeno Porte (ME/EPP);

        $opSimpNac = '-';
        if (is_numeric($cOpSimpNac)) {
            if ($cOpSimpNac === '1') {
                $opSimpNac = 'Não Optante';
            } elseif ($cOpSimpNac === '2') {
                $opSimpNac = 'Optante - Microempreendedor Individual (MEI)';
            } elseif ($cOpSimpNac === '3') {
                $opSimpNac = 'Optante - Microempresa ou Empresa de Pequeno Porte (ME/EPP)';
            }
        }

        return $opSimpNac;
    }

    private function regimeApuracaoTributariaSN($cRegApTribSN): string {
        // NFSe/infNFSe/DPS/infDPS/prest/regTrib/regApTribSN
        // Regime de Apuração Tributária pelo Simples Nacional.
        // Opção para que o contribuinte optante pelo Simples Nacional ME/EPP (opSimpNac = 3) possa indicar, ao emitir o documento fiscal, em qual regime de apuração os tributos federais e municipal estão inseridos, caso tenha ultrapassado algum sublimite ou limite definido para o Simples Nacional.
        // 1 – Regime de apuração dos tributos federais e municipal pelo SN;
        // 2 – Regime de apuração dos tributos federais pelo SN e o ISSQN pela NFS-e conforme respectiva legislação municipal do tributo;
        // 3 – Regime de apuração dos tributos federais e municipal pela NFS-e conforme respectivas legislações federal e municipal de cada tributo;

        $regApTribSN = '-';
        if (is_numeric($cRegApTribSN)) {
            if ($cRegApTribSN === '1') {
                $regApTribSN = 'Regime de apuração dos tributos federais e municipal pelo SN';
            } elseif ($cRegApTribSN === '2') {
                $regApTribSN = 'Regime de apuração dos tributos federais pelo SN e o ISSQN pela NFS-e conforme respectiva legislação municipal do tributo';
            } elseif ($cRegApTribSN === '3') {
                $regApTribSN = 'Regime de apuração dos tributos federais e municipal pela NFS-e conforme respectivas legislações federal e municipal de cada tributo';
            }
        }

        return $regApTribSN;
    }

    private function regimeEspecialTributacao($cRegEspTrib): String {
        // NFSe/infNFSe/DPS/infDPS/prest/regTrib/regEspTrib
        // Tipos de Regimes Especiais de Tributação Municipal:
        // 0 - Nenhum;
        // 1 - Ato Cooperado (Cooperativa);
        // 2 - Estimativa;
        // 3 - Microempresa Municipal;
        // 4 - Notário ou Registrador;
        // 5 - Profissional Autônomo;
        // 6 - Sociedade de Profissionais;

        $regEspTrib = '-';
        if (is_numeric($cRegEspTrib)) {
            if ($cRegEspTrib == 0) {
                $regEspTrib = 'Nenhum';
            } elseif ($cRegEspTrib == 1) {
                $regEspTrib = 'Ato Cooperado (Cooperativa)';
            } elseif ($cRegEspTrib == 2) {
                $regEspTrib = 'Estimativa';
            } elseif ($cRegEspTrib == 3) {
                $regEspTrib = 'Microempresa Municipal';
            } elseif ($cRegEspTrib == 4) {
                $regEspTrib = 'Notário ou Registrador';
            } elseif ($cRegEspTrib == 5) {
                $regEspTrib = 'Profissional Autônomo';
            } elseif ($cRegEspTrib == 6) {
                $regEspTrib = 'Sociedade de Profissionais';
            }
        }

        return $regEspTrib;
    }

    private function tributacaoISSQN($cTribISSQN): string {
        // NFSe/infNFSe/DPS/infDPS/valores/trib/tribMun/tribISSQN
        // Tributação do ISSQN sobre o serviço prestado:
        // 1 - Operação tributável
        // 2 - Imunidade
        // 3 - Exportação de serviço
        // 4 - Não Incidência

        $tribISSQN = '-';
        if (is_numeric($cTribISSQN)) {
            if ($cTribISSQN == 1) {
                $tribISSQN = 'Operação tributável';
            } elseif ($cTribISSQN == 2) {
                $tribISSQN = 'Imunidade';
            } elseif ($cTribISSQN == 3) {
                $tribISSQN = 'Exportação de serviço';
            } elseif ($cTribISSQN == 4) {
                $tribISSQN = 'Não Incidência';
            }
        }

        return $tribISSQN;
    }

    private function tipoImunidade($cTpImunidade): string {
        // NFSe/infNFSe/DPS/infDPS/valores/trib/tribMun/tpImunidade
        // Identificação da Imunidade do ISSQN – somente para o caso de Imunidade.
        // Tipos de Imunidades:
        // 0 - Imunidade (tipo não informado na nota de origem);
        // 1 - Patrimônio, renda ou serviços, uns dos outros (CF88, Art 150, VI, a);
        // 2 - Entidades religiosas e templos de qualquer culto, inclusive suas organizações assistenciais e beneficentes (CF88, Art 150, VI, b);
        // 3 - Patrimônio, renda ou serviços dos partidos políticos, inclusive suas fundações, das entidades sindicais dos trabalhadores, das instituições de educação e de assistência social, sem fins lucrativos, atendidos os requisitos da lei (CF88, Art 150, VI, c);
        // 4 - Livros, jornais, periódicos e o papel destinado a sua impressão (CF88, Art 150, VI, d);
        // 5 - Fonogramas e videofonogramas musicais produzidos no Brasil contendo obras musicais ou literomusicais de autores brasileiros e/ou obras em geral interpretadas por artistas brasileiros bem como os suportes materiais ou arquivos digitais que os contenham, salvo na etapa de replicação industrial de mídias ópticas de leitura a laser.   (CF88, Art 150, VI, e);

        $tpImunidade = '-';
        if (is_numeric($cTpImunidade)) {
            if ($cTpImunidade == 0) {
                $tpImunidade = 'Imunidade (tipo não informado na nota de origem)';
            } elseif ($cTpImunidade == 1) {
                $tpImunidade = 'Patrimônio, renda ou serviços, uns dos outros (CF88, Art 150, VI, a)';
            } elseif ($cTpImunidade == 2) {
                $tpImunidade = 'Entidades religiosas e templos de qualquer culto, inclusive suas organizações assistenciais e beneficentes (CF88, Art 150, VI, b)';
            } elseif ($cTpImunidade == 3) {
                $tpImunidade = 'Patrimônio, renda ou serviços dos partidos políticos, inclusive suas fundações, das entidades sindicais dos trabalhadores, das instituições de educação e de assistência social, sem fins lucrativos, atendidos os requisitos da lei (CF88, Art 150, VI, c)';
            } elseif ($cTpImunidade == 4) {
                $tpImunidade = 'Livros, jornais, periódicos e o papel destinado a sua impressão (CF88, Art 150, VI, d)';
            } elseif ($cTpImunidade == 5) {
                $tpImunidade = 'Fonogramas e videofonogramas musicais produzidos no Brasil contendo obras musicais ou literomusicais de autores brasileiros e/ou obras em geral interpretadas por artistas brasileiros bem como os suportes materiais ou arquivos digitais que os contenham, salvo na etapa de replicação industrial de mídias ópticas de leitura a laser.   (CF88, Art 150, VI, e)';
            }
        }

        return $tpImunidade;
    }

    private function suspensaoExigibilidadeISSQN($cTpSusp): string {
        // NFSe/infNFSe/DPS/infDPS/valores/trib/tribMun/exigSusp/tpSusp
        // Opção para Exigibilidade Suspensa:
        // 1 - Exigibilidade do ISSQN Suspensa por Decisão Judicial;
        // 2 - Exigibilidade do ISSQN Suspensa por Processo Administrativo;

        $tpSusp = '-';
        if (is_numeric($cTpSusp)) {
            if ($cTpSusp == 1) {
                $tpSusp = 'Exigibilidade do ISSQN Suspensa por Decisão Judicial';
            } elseif ($cTpSusp == 2) {
                $tpSusp = 'Exigibilidade do ISSQN Suspensa por Processo Administrativo';
            }
        }

        return $tpSusp;
    }

    private function tipoRetencaoISSQN($cTpRetISSQN): string {
        // NFSe/infNFSe/DPS/infDPS/valores/trib/tribMun/tpRetISSQN
        // Tipo de retencao do ISSQN:
        // 1 - Não Retido;
        // 2 - Retido pelo Tomador;
        // 3 - Retido pelo Intermediario;

        $tpRetISSQN = '-';
        if (is_numeric($cTpRetISSQN)) {
            if ($cTpRetISSQN == 1) {
                $tpRetISSQN = 'Não Retido';
            } elseif ($cTpRetISSQN == 2) {
                $tpRetISSQN = 'Retido pelo Tomador';
            } elseif ($cTpRetISSQN == 3) {
                $tpRetISSQN = 'Retido pelo Intermediario';
            }
        }

        return $tpRetISSQN;
    }

    private function retencaoPisCofins($cTpRetPisCofins): string {
        // NFSe/infNFSe/DPS/infDPS/valores/trib/tribFed/piscofins/tpRetPisCofins
        // Tipo de retenção ao do PIS/COFINS:
        // 1 - PIS/COFINS Retido;
        // 2 - PIS/COFINS Não Retido;
        // 3 - PIS Retido/COFINS Não Retido;
        // 4 - PIS Não Retido/COFINS Retido;

        $tpRetPisCofins = '-';
        if (is_numeric($cTpRetPisCofins)) {
            if ($cTpRetPisCofins == 1) {
                $tpRetPisCofins = 'Retido';
            } elseif ($cTpRetPisCofins == 2) {
                $tpRetPisCofins = 'Não Retido';
            } elseif ($cTpRetPisCofins == 3) {
                $tpRetPisCofins = 'PIS Retido/COFINS Não Retido';
            } elseif ($cTpRetPisCofins == 4) {
                $tpRetPisCofins = 'PIS Não Retido/COFINS Retido';
            }
        }

        return $tpRetPisCofins;
    }
}
