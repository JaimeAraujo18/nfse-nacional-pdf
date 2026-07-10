<?php

namespace NfsePdf;

use TCPDF;

class NfsePdfGenerator {
    private $pdf;
    private $data;
    private $margin = 5;
    private $font = 'helvetica';
    private $cancelada = false;
    private $substituida = false;
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
        $this->pdf->SetFont($this->font, '', 7);
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

    public function setCancelada(bool $cancelada) {
        $this->cancelada = $cancelada;

        return $this;
    }

    public function setSubstituida(bool $substituida) {
        $this->substituida = $substituida;

        return $this;
    }

    public function getTomadorCidadeIBGE(): int {
        return (int) $this->data['tomador']['ibgeMunicipio'];
    }

    public function setTomadorCidadeUF(string $cidade, string $uf) {
        $this->data['tomador']['municipio'] = $cidade;
        $this->data['tomador']['uf'] = $uf;
    }

    public function getLocalPrestacaoIbge(): int {
        return (int) $this->data['localPrestacaoIbge'];
    }

    public function setLocalPrestacaoUF(string $uf) {
        $this->data['localPrestacaoUf'] = $uf;

        return $this;
    }

    public function getLocalIncidenciaIbge(): int {
        return (int) $this->data['localIncidenciaIbge'];
    }

    public function setLocalIncidenciaUF(string $uf) {
        $this->data['localIncidenciaUf'] = $uf;

        return $this;
    }

    public function getDestinatarioIbge(): int {
        return (int) $this->data['destinatario']['ibgeMunicipio'];
    }

    public function setDestinatarioUF(string $cidade, string $uf) {
        $this->data['destinatario']['municipio'] = $cidade;
        $this->data['destinatario']['uf'] = $uf;

        return $this;
    }

    public function getIbsCbsLocalidadeIncidIbge(): int {
        return (int) $this->data['ibsCbs']['localidadeIncidIbge'];
    }

    public function setIbsCbsLocalidadeIncidUF(string $uf) {
        $this->data['ibsCbs']['localidadeIncidUf'] = $uf;

        return $this;
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
            'localPrestacaoIbge' => (string) $dps->serv->locPrest->cLocPrestacao,
            'localPrestacaoPais' => (string) $dps->serv->locPrest->cPaisPrestacao,
            'localIncidencia' => (string) $infNFSe->xLocIncid,
            'localIncidenciaIbge' => (string) $infNFSe->cLocIncid,
            'localIncidenciaPais' => (string) $dps->valores->trib->tribMun->cPaisResult,
            'tribNac' => (string) $infNFSe->xTribNac,
            'dataProcessamento' => $this->formatDateTime((string) $infNFSe->dhProc),
            'numeroDFSe' => (string) $infNFSe->nDFSe,
            'tpEmit' => (string) $dps->tpEmit,
            'cStat' => (string) $infNFSe->cStat,
            'ambGer' => (string) $infNFSe->ambGer,
            'tpAmb' => (string) $dps->tpAmb,
            'xOutInf' => (string) $infNFSe->xOutInf,
            'emitente' => [
                'cnpj' => $this->formatCnpjCpf((string) $infNFSe->emit->CNPJ),
                'inscricaoMunicipal' => ((string) $infNFSe->emit->IM) ?: '-',
                'nome' => (string) $infNFSe->emit->xNome,
                'email' => (string) $infNFSe->emit->email,
                'fone' => $this->formatPhone((string) $infNFSe->emit->fone),
                'logradouro' => (string) $infNFSe->emit->enderNac->xLgr,
                'numero' => (string) $infNFSe->emit->enderNac->nro,
                'complemento' => (string) $infNFSe->emit->enderNac->xCpl,
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
                'descTribMun' => (string) $dps->serv->cServ->xTribMun,
                'descricao' => (string) $dps->serv->cServ->xDescServ,
                'nbs' => (string) $dps->serv->cServ->cNBS,
                'descNbs' => (string) $dps->serv->cServ->xNBS,
                'infoComp' => (string) $dps->serv->infoCompl->xInfComp,
            ],
            'valores' => [
                'servico' => (float) $dps->valores->vServPrest->vServ,
                'descontoIncondicionado' => $dps->valores->vDescCondIncond->vDescIncond ?? '-',
                'descontoCondicionado' => $dps->valores->vDescCondIncond->vDescCond ?? '-',
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
                'totalRetencoes' => $infNFSe->valores->vTotalRet ?? '-',
                'totalTributosFederais' => (float) $dps->valores->trib->totTrib->vTotTrib->vTotTribFed,
                'totalTributosEstaduais' => (float) $dps->valores->trib->totTrib->vTotTrib->vTotTribEst,
                'totalTributosMunicipais' => (float) $dps->valores->trib->totTrib->vTotTrib->vTotTribMun,
            ],
            'dps' => [
                'numero' => (string) $dps->nDPS,
                'serie' => (string) $dps->serie,
                'competencia' => $this->formatDate((string) $dps->dCompet),
                'competenciaISO' => (string) $dps->dCompet,
                'dataEmissao' => $this->formatDateTime((string) $dps->dhEmi),
            ],
            'tributacao' => [
                'existeTribMun' => isset($dps->valores->trib->tribMun) && count($dps->valores->trib->tribMun) > 0,
                'tipoTributacaoISSQN' => (string) $dps->valores->trib->tribMun->tribISSQN,
                'regimeEspecialTributacao' => (string) $dps->prest->regTrib->regEspTrib,
                'tipoImunidade' => (string) $dps->valores->trib->tribMun->tpImunidade ?? '-',
                'tipoSuspensao' => (string) $dps->valores->trib->tribMun->exigSusp->tpSusp ?? '-',
                'nProcessoSuspensao' => (string) $dps->valores->trib->tribMun->exigSusp->nProcesso ?? '-',
                'tipoBeneficioMunicipal' => (string) $infNFSe->valores->tpBM,
                'percentualAliquotaAplicadaISSQN' => (float) $infNFSe->valores->pAliqAplic ?? '-',
                'tipoRetencaoISSQN' => (string) $dps->valores->trib->tribMun->tpRetISSQN,
                'tipoRetencaoPisCofins' => (string) ($dps->valores->trib->tribFed->piscofins->tpRetPisCofins ?? '-'),
            ],
            'ibsCbs' => $this->parseIbsCbs($infNFSe, $dps),
            'infoComplementares' => $this->parseInfoComplementares($infNFSe, $dps),
            'destinatario' => $this->parseDestinatario($dps),
        ];


        $data['tomador']['existe'] = ($data['tomador']['doc'] !== '' || $data['tomador']['nome'] !== '');

        $data['ibsCbs']['valorTotalIbsCbs'] = (float) $data['ibsCbs']['valorTotalIbs'] + (float) $data['ibsCbs']['valorTotalCbs'];

        // Quando há IBS/CBS a prefeitura/SEFIN já devolve o total pronto (vTotNF); sem IBS/CBS, líquido = líquido normal
        $data['ibsCbs']['liquidoComIbsCbs'] = !empty($data['ibsCbs']['classTrib'])
            ? (float) $infNFSe->IBSCBS->totCIBS->vTotNF
            : (float) $data['valores']['liquido'];

        // Soma dos 5 componentes previstos na NT-008 (Desconto Incondicionado + IBS/CBS + ISSQN + PIS + COFINS)
        $data['ibsCbs']['exclusoesReducoes'] = (float) $data['valores']['descontoIncondicionado']
            + (float) $data['ibsCbs']['exclusoesReducoes']
            + (float) $data['valores']['ISSQN']
            + (float) $data['valores']['PIS']
            + (float) $data['valores']['COFINS'];

        $data['valores']['servico'] = $this->money($data['valores']['servico']);
        $data['valores']['descontoIncondicionado'] = $this->money($data['valores']['descontoIncondicionado']);
        $data['valores']['descontoCondicionado'] = $this->money($data['valores']['descontoCondicionado']);
        $data['valores']['totalDeducaoReducao'] = $this->money($data['valores']['totalDeducaoReducao']);
        $data['valores']['calculoBeneficioMunicipal'] = $this->money($data['valores']['calculoBeneficioMunicipal']);
        $data['valores']['baseCalculoISSQN'] = $this->money($data['valores']['baseCalculoISSQN']);
        $data['valores']['ISSQN'] = $this->money($data['valores']['ISSQN']);
        $data['valores']['IRRF'] = $this->money($data['valores']['IRRF']);
        $data['valores']['CP'] = $this->money($data['valores']['CP']);
        $data['valores']['CSLL'] = $this->money($data['valores']['CSLL']);
        $data['valores']['PIS'] = $this->money($data['valores']['PIS']);
        $data['valores']['COFINS'] = $this->money($data['valores']['COFINS']);
        $data['valores']['totalRetencoes'] = $this->money($data['valores']['totalRetencoes']);
        $data['valores']['liquido'] = $this->money($data['valores']['liquido']);
        $data['valores']['totalTributosFederais'] = $this->money($data['valores']['totalTributosFederais']);
        $data['valores']['totalTributosEstaduais'] = $this->money($data['valores']['totalTributosEstaduais']);
        $data['valores']['totalTributosMunicipais'] = $this->money($data['valores']['totalTributosMunicipais']);

        $data['tributacao']['percentualAliquotaAplicadaISSQN'] = $this->money($data['tributacao']['percentualAliquotaAplicadaISSQN'], 2, true);

        $data['ibsCbs']['exclusoesReducoes'] = $this->money($data['ibsCbs']['exclusoesReducoes']);
        $data['ibsCbs']['baseCalculo'] = $this->money($data['ibsCbs']['baseCalculo']);
        $data['ibsCbs']['reducaoAliqIbsUf'] = $this->money($data['ibsCbs']['reducaoAliqIbsUf'], 2, true);
        $data['ibsCbs']['reducaoAliqIbsMun'] = $this->money($data['ibsCbs']['reducaoAliqIbsMun'], 2, true);
        $data['ibsCbs']['reducaoAliqCbs'] = $this->money($data['ibsCbs']['reducaoAliqCbs'], 2, true);
        $data['ibsCbs']['aliqIbsUf'] = $this->money($data['ibsCbs']['aliqIbsUf'], 2, true);
        $data['ibsCbs']['aliqEfetIbsUf'] = $this->money($data['ibsCbs']['aliqEfetIbsUf'], 2, true);
        $data['ibsCbs']['valorIbsUf'] = $this->money($data['ibsCbs']['valorIbsUf']);
        $data['ibsCbs']['aliqIbsMun'] = $this->money($data['ibsCbs']['aliqIbsMun'], 2, true);
        $data['ibsCbs']['aliqEfetIbsMun'] = $this->money($data['ibsCbs']['aliqEfetIbsMun'], 2, true);
        $data['ibsCbs']['valorIbsMun'] = $this->money($data['ibsCbs']['valorIbsMun']);
        $data['ibsCbs']['valorTotalIbs'] = $this->money($data['ibsCbs']['valorTotalIbs']);
        $data['ibsCbs']['aliqCbs'] = $this->money($data['ibsCbs']['aliqCbs'], 2, true);
        $data['ibsCbs']['aliqEfetCbs'] = $this->money($data['ibsCbs']['aliqEfetCbs'], 2, true);
        $data['ibsCbs']['valorTotalCbs'] = $this->money($data['ibsCbs']['valorTotalCbs']);
        $data['ibsCbs']['valorTotalIbsCbs'] = $this->money($data['ibsCbs']['valorTotalIbsCbs']);
        $data['ibsCbs']['liquidoComIbsCbs'] = $this->money($data['ibsCbs']['liquidoComIbsCbs']);

        $this->data = $data;

        return $this;
    }

    private function parseIbsCbs($infNFSe, $dps): array {
        $empty = [
            'cst' => '',
            'classTrib' => '',
            'finNFSe' => '',
            'cIndOp' => '',
            'localidadeIncidIbge' => '',
            'localidadeIncid' => '',
            'exclusoesReducoes' => 0.0,
            'baseCalculo' => 0.0,
            'reducaoAliqIbsUf' => 0.0,
            'reducaoAliqIbsMun' => 0.0,
            'reducaoAliqCbs' => 0.0,
            'aliqIbsUf' => 0.0,
            'aliqEfetIbsUf' => 0.0,
            'valorIbsUf' => 0.0,
            'aliqIbsMun' => 0.0,
            'aliqEfetIbsMun' => 0.0,
            'valorIbsMun' => 0.0,
            'valorTotalIbs' => 0.0,
            'aliqCbs' => 0.0,
            'aliqEfetCbs' => 0.0,
            'valorTotalCbs' => 0.0,
        ];

        // O bloco IBSCBS só existe em DPS/NFS-e emitidas já sob a reforma tributária (IBS/CBS)
        if (!isset($dps->IBSCBS) || count($dps->IBSCBS) === 0 || !isset($infNFSe->IBSCBS) || count($infNFSe->IBSCBS) === 0) {
            return $empty;
        }

        return [
            'cst' => (string) $dps->IBSCBS->valores->trib->gIBSCBS->CST,
            'classTrib' => (string) $dps->IBSCBS->valores->trib->gIBSCBS->cClassTrib,
            'finNFSe' => (string) $dps->IBSCBS->finNFSe,
            'cIndOp' => (string) $dps->IBSCBS->cIndOp,
            'localidadeIncidIbge' => (string) $infNFSe->IBSCBS->cLocalidadeIncid,
            'localidadeIncid' => (string) $infNFSe->IBSCBS->xLocalidadeIncid,
            'exclusoesReducoes' => (float) $infNFSe->IBSCBS->valores->vCalcReeRepRes,
            'baseCalculo' => (float) $infNFSe->IBSCBS->valores->vBC,
            'reducaoAliqIbsUf' => (float) $infNFSe->IBSCBS->valores->uf->pRedAliqUF,
            'reducaoAliqIbsMun' => (float) $infNFSe->IBSCBS->valores->mun->pRedAliqMun,
            'reducaoAliqCbs' => (float) $infNFSe->IBSCBS->valores->fed->pRedAliqCBS,
            'aliqIbsUf' => (float) $infNFSe->IBSCBS->valores->uf->pIBSUF,
            'aliqEfetIbsUf' => (float) $infNFSe->IBSCBS->valores->uf->pAliqEfetUF,
            'valorIbsUf' => (float) $infNFSe->IBSCBS->totCIBS->gIBS->gIBSUFTot->vIBSUF,
            'aliqIbsMun' => (float) $infNFSe->IBSCBS->valores->mun->pIBSMun,
            'aliqEfetIbsMun' => (float) $infNFSe->IBSCBS->valores->mun->pAliqEfetMun,
            'valorIbsMun' => (float) $infNFSe->IBSCBS->totCIBS->gIBS->gIBSMunTot->vIBSMun,
            'valorTotalIbs' => (float) $infNFSe->IBSCBS->totCIBS->gIBS->vIBSTot,
            'aliqCbs' => (float) $infNFSe->IBSCBS->valores->fed->pCBS,
            'aliqEfetCbs' => (float) $infNFSe->IBSCBS->valores->fed->pAliqEfetCBS,
            'valorTotalCbs' => (float) $infNFSe->IBSCBS->totCIBS->gCBS->vCBS,
        ];
    }

    /**
     * Acessa um caminho de elementos opcionais do XML sem gerar warning de
     * "read property on null" quando algum nó intermediário não existe
     * (ex.: blocos só presentes em NFS-e emitidas sob a reforma do IBS/CBS).
     */
    private function safeXmlValue($node, array $path): string {
        $current = $node;

        foreach ($path as $prop) {
            if (!isset($current->$prop)) {
                return '';
            }

            $current = $current->$prop;
        }

        return (string) $current;
    }

    private function parseDestinatario($dps): array {
        $empty = [
            'existe' => false,
            'doc' => '',
            'nome' => '',
            'email' => '',
            'fone' => '',
            'logradouro' => '',
            'numero' => '',
            'complemento' => '',
            'bairro' => '',
            'ibgeMunicipio' => '',
            'cep' => '',
        ];

        $doc = $this->safeXmlValue($dps, ['IBSCBS', 'dest', 'CNPJ']);
        if ($doc === '') {
            $doc = $this->safeXmlValue($dps, ['IBSCBS', 'dest', 'CPF']);
        }

        $nome = $this->safeXmlValue($dps, ['IBSCBS', 'dest', 'xNome']);

        if ($doc === '' && $nome === '') {
            return $empty;
        }

        return [
            'existe' => true,
            'doc' => $this->formatCnpjCpf($doc),
            'nome' => $nome,
            'email' => $this->safeXmlValue($dps, ['IBSCBS', 'dest', 'email']),
            'fone' => $this->formatPhone($this->safeXmlValue($dps, ['IBSCBS', 'dest', 'fone'])),
            'logradouro' => $this->safeXmlValue($dps, ['IBSCBS', 'dest', 'end', 'xLgr']),
            'numero' => $this->safeXmlValue($dps, ['IBSCBS', 'dest', 'end', 'nro']),
            'complemento' => $this->safeXmlValue($dps, ['IBSCBS', 'dest', 'end', 'xCpl']),
            'bairro' => $this->safeXmlValue($dps, ['IBSCBS', 'dest', 'end', 'xBairro']),
            'ibgeMunicipio' => $this->safeXmlValue($dps, ['IBSCBS', 'dest', 'end', 'endNac', 'cMun']),
            'cep' => $this->formatCep($this->safeXmlValue($dps, ['IBSCBS', 'dest', 'end', 'endNac', 'CEP'])),
        ];
    }

    private function parseInfoComplementares($infNFSe, $dps): array {
        $partes = [];

        $add = function (string $label, string $valor) use (&$partes) {
            if ($valor !== '') {
                $partes[] = $label . $valor;
            }
        };

        $add('Inf. Cont.: ', $this->safeXmlValue($dps, ['serv', 'infoCompl', 'xInfComp']));
        $add('NFS-e Subst.: ', $this->safeXmlValue($dps, ['subst', 'chSubstda']));
        $add('Doc. Ref.: ', $this->safeXmlValue($dps, ['serv', 'infoCompl', 'docRef']));
        $add('Cod. Obra: ', $this->safeXmlValue($dps, ['serv', 'obra', 'cObra']));
        $add('Insc. Imob.: ', $this->safeXmlValue($dps, ['IBSCBS', 'imovel', 'inscImobFisc']));
        $add('Cod. Evt.: ', $this->safeXmlValue($dps, ['serv', 'atvEvento', 'idAtvEvt']));
        $add('Doc. Tec.: ', $this->safeXmlValue($dps, ['serv', 'infoCompl', 'idDocTec']));
        $add('Núm. Ped.: ', $this->safeXmlValue($dps, ['serv', 'infoCompl', 'xPed']));
        $add('Item Ped.: ', $this->safeXmlValue($dps, ['serv', 'infoCompl', 'gItemPed', 'xItemPed']));
        $add('Inf. A. T. Mun.: ', $this->safeXmlValue($infNFSe, ['xOutInf']));

        $totalFed = $this->money((float) $this->safeXmlValue($dps, ['valores', 'trib', 'totTrib', 'vTotTrib', 'vTotTribFed']));
        $totalEst = $this->money((float) $this->safeXmlValue($dps, ['valores', 'trib', 'totTrib', 'vTotTrib', 'vTotTribEst']));
        $totalMun = $this->money((float) $this->safeXmlValue($dps, ['valores', 'trib', 'totTrib', 'vTotTrib', 'vTotTribMun']));

        $totaisTributos = "Totais Aproximados dos Tributos cfe. Lei nº 12.741/2012: Federais: $totalFed ; Estaduais: $totalEst ; Municipais: $totalMun";

        return [
            'texto' => implode(' | ', $partes),
            'totaisTributos' => $totaisTributos,
        ];
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
        $this->addDestinatario();
        $this->addHorizontalLine();
        $this->addIntermediario();
        $this->addHorizontalLine();
        $this->addServico();
        $this->addHorizontalLine();
        $this->addTributacaoMunicipal();
        $this->addHorizontalLine();
        $this->addTributacaoFederal();
        $this->addHorizontalLine();

        if (!empty($this->data['ibsCbs']['classTrib'])) {
            $this->addTributacaoIbsCbs();
            $this->addHorizontalLine();
        }

        $this->addValores();
        $this->addHorizontalLine();
        $this->addInformacoesComplementares();


        // Draw border around the entire document after all content is added
        // This ensures it encompasses everything including "INFORMAÇÕES COMPLEMENTARES"
        $this->drawDocumentBorder();

        if ($this->cancelada) {
            $this->drawWatermark('CANCELADA');
        } elseif ($this->substituida) {
            $this->drawWatermark('SUBSTITUÍDA');
        }

        return $this->pdf;
    }

    private function drawWatermark(string $texto) {
        $this->pdf->SetFont('helvetica', 'B', 50);
        $this->pdf->SetTextColor(165, 165, 165); // cinza K35 aproximado
        $this->pdf->StartTransform();
        $this->pdf->Rotate(45, 105, 148.5); // gira em torno do centro da página A4 (210x297mm)
        $this->pdf->SetXY(5, 138);
        $this->pdf->Cell(200, 20, $texto, 0, 0, 'C');
        $this->pdf->StopTransform();
        $this->pdf->SetTextColor(0, 0, 0);
    }

    private function drawDocumentBorder() {
        // Draw a rectangle border around the entire document
        // Using absolute coordinates from page top-left corner
        $pageWidth = 210; // A4 width in mm
        $pageHeight = 297; // A4 height in mm

        $x1 = $this->margin - 3;
        $y1 = $this->margin - 3;
        $width = $pageWidth - (2 * $x1);  // Total width minus both margins (mantém a borda simétrica)
        $height = $pageHeight - (2 * $y1); // Total height minus both margins (mantém a borda simétrica)

        // Espessura de 1pt (NT-008 2.2.3) em mm: 1pt = 0,3528mm
        $this->pdf->SetLineWidth(0.3528);

        // Draw rectangle border using absolute coordinates from page top
        // Rect(x, y, width, height, style)
        $this->pdf->Rect($x1, $y1, $width, $height, 'D');
    }

    private function addHorizontalLine() {
        $y = $this->pdf->GetY();
        $pageWidth = 210; // A4 width in mm
        $rightEdge = $pageWidth - $this->margin;
        // Espessura de 0,5pt (NT-008 2.2.3) em mm: 0,5pt = 0,1764mm
        $this->pdf->SetLineWidth(0.1764);
        $this->pdf->Line($this->margin, $y, $rightEdge, $y);
        $this->pdf->Ln(1);
    }

    private function addHeader() {
        $startY = $this->pdf->GetY();
        $col4X = 147;

        // Sombreamento cinza 5% do cabeçalho (NT-008 2.2.3)
        $this->pdf->SetFillColor(242, 242, 242);
        $this->pdf->Rect($this->margin, $startY, 210 - (2 * $this->margin), 12, 'F');

        // Left column - Logo image
        $logoPath = __DIR__ . '/../assets/logo-nfse-assinatura-horizontal.png';
        if (file_exists($logoPath)) {
            $this->pdf->Image($logoPath, $this->margin, $startY, 50, 0, 'PNG', '', '', false, 300, '', false, false, 0, false, false, false);
        }

        // Center column - Main title
        $centerX = 62;
        $this->pdf->SetXY($centerX, $startY);
        $this->pdf->SetFont($this->font, 'B', 9);
        $this->pdf->Cell(50, 4, 'DANFSe v2.0', 0, 0, 'C');
        $this->pdf->SetXY($centerX, $startY + 4);
        $this->pdf->SetFont($this->font, 'B', 9);
        $this->pdf->Cell(50, 4, 'Documento Auxiliar da NFS-e', 0, 0, 'C');

        if ($this->data['tpAmb'] === '2') {
            $this->pdf->SetXY($centerX, $startY + 8);
            $this->pdf->SetFont($this->font, 'B', 9);
            $this->pdf->SetTextColor(255, 0, 0);
            $this->pdf->Cell(50, 4, 'NFS-e SEM VALIDADE JURÍDICA', 0, 0, 'C');
            $this->pdf->SetTextColor(0, 0, 0);
        }

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

        $municipioUf = $this->data['localEmissao'] . ' / ' . ($this->data['emitente']['uf'] ?? '');

        $rowMunicipalityY = $startY;
        $this->pdf->SetXY($col4X, $startY);
        $this->pdf->SetFont($this->font, '', 8);
        $this->pdf->Cell(57, 3, 'Município: ' . $municipioUf, 0, 1, 'L');
        $this->pdf->SetXY($col4X, $rowMunicipalityY += 3);
        $this->pdf->SetFont($this->font, '', 6);
        $this->pdf->Cell(57, 2.5, 'Ambiente Gerador: ' . $this->ambienteGerador($this->data['ambGer']), 0, 1, 'L');
        $this->pdf->SetXY($col4X, $rowMunicipalityY += 2.5);
        $this->pdf->Cell(57, 2.5, 'Tipo de Ambiente: ' . $this->tipoAmbiente($this->data['tpAmb']), 0, 1, 'L');

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
        $this->pdf->Cell($col1W + $col2W + $col3W + $col4W, 4, 'CHAVE DE ACESSO DA NFS-E', 0, 1, 'L');
        $this->pdf->SetFont($this->font, '', 7);
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
        $this->pdf->Cell($col1W, 4, 'NÚMERO DA NFS-E', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row1Y);
        $this->pdf->Cell($col2W, 4, 'COMPETÊNCIA DA NFS-E', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row1Y);
        $this->pdf->Cell($col3W, 4, 'DATA E HORA DA EMISSÃO DA NFS-E', 0, 0, 'L');

        // Second row - NFS-e data
        $this->pdf->SetFont($this->font, '', 7);
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
        $this->pdf->Cell($col1W, 4, 'NÚMERO DA DPS', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row3Y);
        $this->pdf->Cell($col2W, 4, 'SÉRIE DA DPS', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row3Y);
        $this->pdf->Cell($col3W, 4, 'DATA E HORA DA EMISSÃO DA DPS', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row3Y);
        $this->pdf->Cell($col4W, 4, '', 0, 1, 'L');

        // Fourth row - DPS data (authenticity message in 4th column, below QR code)
        $this->pdf->SetFont($this->font, '', 7);
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

        // Fifth row - Emitente da NFS-e / Situação da NFS-e / Finalidade
        $row5Y = $row4Y + 4;
        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->SetXY($col1X, $row5Y);
        $this->pdf->Cell($col1W, 4, 'EMITENTE DA NFS-E', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row5Y);
        $this->pdf->Cell($col2W, 4, 'SITUAÇÃO DA NFS-E', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row5Y);
        $this->pdf->Cell($col3W, 4, 'FINALIDADE', 0, 1, 'L');

        // Sixth row - data
        $this->pdf->SetFont($this->font, '', 7);
        $row6Y = $row5Y + 4;
        $this->pdf->SetXY($col1X, $row6Y);
        $this->pdf->Cell($col1W, 4, $this->emitenteNFSe($this->data['tpEmit']), 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row6Y);
        $this->pdf->Cell($col2W, 4, $this->situacaoNFSe($this->data['cStat']), 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row6Y);
        $this->pdf->Cell($col3W, 4, $this->finalidadeNFSe($this->data['ibsCbs']['finNFSe']), 0, 1, 'L');

        $this->pdf->Ln(1);
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
        $this->pdf->Cell($col1W, 4, 'PRESTADOR / FORNECEDOR', 0, 0, 'L');
        $this->pdf->SetFont($this->font, 'B', 6);
        $this->pdf->SetXY($col2X, $startY);
        $this->pdf->Cell($col2W, 4, 'CNPJ / CPF / NIF', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $startY);
        $this->pdf->Cell($col3W, 4, 'Inscrição Municipal', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $startY);
        $this->pdf->Cell($col4W, 4, 'Telefone', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 7);
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
        $this->pdf->SetFont($this->font, 'B', 6);
        $this->pdf->SetXY($col1X, $row2Y);
        $this->pdf->Cell($col1W, 4, 'Nome / Nome Empresarial', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row2Y);
        $this->pdf->Cell($col2W, 4, '', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row2Y);
        $this->pdf->Cell($col3W, 4, 'E-mail', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row2Y);
        $this->pdf->Cell($col4W, 4, '', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 7);
        $this->pdf->SetXY($col1X, $row2Y + 4);
        $this->pdf->MultiCell($col1W + $col2W, 4, $emit['nome'], 0, 'L');

        $row3Y = max($this->pdf->GetY(), $row2Y + 4); // altura abaixo do multicell, que pode quebrar de linha

        $this->pdf->SetXY($col3X, $row2Y + 4);
        $this->pdf->Cell($col3W, 4, $emit['email'], 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row2Y + 4);
        $this->pdf->Cell($col4W, 4, '', 0, 1, 'L');

        // Header row
        $this->pdf->SetFont($this->font, 'B', 6);
        $this->pdf->SetXY($col1X, $row3Y);
        $this->pdf->Cell($col1W, 4, 'Endereço', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row3Y);
        $this->pdf->Cell($col2W, 4, '', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row3Y);
        $this->pdf->Cell($col3W, 4, 'Município', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row3Y);
        $this->pdf->Cell($col4W, 4, 'Código IBGE / CEP', 0, 1, 'L');

        $complementoEmit = !empty($emit['complemento']) ? ', ' . $emit['complemento'] : '';
        $endereco = $emit['logradouro'] . ', ' . $emit['numero'] . $complementoEmit . ', ' . $emit['bairro'];

        // Data row
        $this->pdf->SetFont($this->font, '', 7);
        $this->pdf->SetXY($col1X, $row3Y + 4);
        $this->pdf->MultiCell($col1W + $col2W, 4, $endereco, 0, 'L');

        $row4Y = max($this->pdf->GetY(), $row3Y + 4); // altura abaixo do multicell, que pode quebrar de linha

        $this->pdf->SetXY($col3X, $row3Y + 4);
        $this->pdf->Cell($col3W, 4, $this->data['localEmissao'] . ' - ' . $emit['uf'], 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row3Y + 4);
        $this->pdf->Cell($col4W, 4, $emit['municipio'] . ' / ' . $emit['cep'], 0, 1, 'L');

        // Header row
        $this->pdf->SetXY($col1X, $row4Y);
        $this->pdf->setFont($this->font, 'B', 6);
        $this->pdf->Cell($col1W, 4, 'Simples Nacional na Data de Competência', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $this->pdf->GetY());
        $this->pdf->Cell($col3W + $col4W, 4, 'Regime de Apuração Tributária pelo SN', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 7);
        $this->pdf->SetXY($col1X, $this->pdf->GetY());
        $this->pdf->Cell($col1W, 4, $this->optanteSimplesNacional($emit['optanteSimplesNacional']), 0, 0, 'L');
        $this->pdf->SetXY($col3X, $this->pdf->GetY());
        $this->pdf->MultiCell($col3W + $col4W, 4, $this->regimeApuracaoTributariaSN($emit['regimeApuracaoTributariaSN']), 0, 'L');
        $this->pdf->Ln(1);
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

        if (empty($toma['existe'])) {
            $this->pdf->SetFont($this->font, 'B', 7);
            $this->pdf->Cell(0, 0, 'TOMADOR/ADQUIRENTE DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e', 0, 1, 'C');
            $this->pdf->Ln(1);

            return;
        }

        $startY = $this->pdf->GetY();

        // Header row
        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->SetXY($col1X, $startY);
        $this->pdf->Cell($col1W, 4, 'TOMADOR DO SERVIÇO', 0, 0, 'L');
        $this->pdf->SetFont($this->font, 'B', 6);
        $this->pdf->SetXY($col2X, $startY);
        $this->pdf->Cell($col2W, 4, 'CNPJ / CPF / NIF', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $startY);
        $this->pdf->Cell($col3W, 4, 'Inscrição Municipal', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $startY);
        $this->pdf->Cell($col4W, 4, 'Telefone', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 7);
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
        $this->pdf->SetFont($this->font, 'B', 6);
        $this->pdf->SetXY($col1X, $row2Y);
        $this->pdf->Cell($col1W, 4, 'Nome / Nome Empresarial', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row2Y);
        $this->pdf->Cell($col2W, 4, '', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row2Y);
        $this->pdf->Cell($col3W, 4, 'E-mail', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row2Y);
        $this->pdf->Cell($col4W, 4, '', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 7);
        $this->pdf->SetXY($col1X, $row2Y + 4);
        $this->pdf->MultiCell($col1W + $col2W, 4, $toma['nome'], 0, 'L');

        $row3Y = max($this->pdf->GetY(), $row2Y + 4); // altura abaixo do multicell, que pode quebrar de linha

        $this->pdf->SetXY($col3X, $row2Y + 4);
        $this->pdf->Cell($col3W + $col4W, 4, $toma['email'], 0, 0, 'L');

        // Header row
        $this->pdf->SetFont($this->font, 'B', 6);
        $this->pdf->SetXY($col1X, $row3Y);
        $this->pdf->Cell($col1W, 4, 'Endereço', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row3Y);
        $this->pdf->Cell($col2W, 4, '', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row3Y);
        $this->pdf->Cell($col3W, 4, 'Município', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row3Y);
        $this->pdf->Cell($col4W, 4, 'Código IBGE / CEP', 0, 1, 'L');

        [$tomadorCidade, $tomadorEndereco] = $this->enderecoTomador($toma);

        // Data row
        $this->pdf->SetFont($this->font, '', 7);
        $this->pdf->SetXY($col1X, $row3Y + 4);
        $this->pdf->MultiCell($col1W + $col2W, 4, $tomadorEndereco, 0, 'L');

        $row4Y = max($this->pdf->GetY(), $row2Y + 4); // altura abaixo do multicell, que pode quebrar de linha

        $this->pdf->SetXY($col3X, $row3Y + 4);
        $this->pdf->Cell($col3W, 4, $tomadorCidade, 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row3Y + 4);
        $this->pdf->Cell($col4W, 4, $toma['ibgeMunicipio'] . ' / ' . $toma['cep'], 0, 1, 'L');

        $this->pdf->setY($row4Y); // após o final do endereco
        $this->pdf->Ln(1);
    }

    private function addDestinatario() {
        $col1X = $this->margin;
        $col2X = 47;
        $col3X = 97;
        $col4X = 147;
        $col1W = 45;
        $col2W = 50;
        $col3W = 50;
        $col4W = 50;

        $dest = $this->data['destinatario'];
        $toma = $this->data['tomador'];

        if (empty($dest['existe'])) {
            $this->pdf->SetFont($this->font, 'B', 7);
            $this->pdf->Cell(0, 0, 'DESTINATÁRIO DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e', 0, 1, 'C');
            $this->pdf->Ln(1);

            return;
        }

        if (!empty($toma['doc']) && $dest['doc'] === $toma['doc']) {
            $this->pdf->SetFont($this->font, 'B', 7);
            $this->pdf->Cell(0, 0, 'O DESTINATÁRIO É O PRÓPRIO TOMADOR/ADQUIRENTE DA OPERAÇÃO', 0, 1, 'C');
            $this->pdf->Ln(1);

            return;
        }

        $startY = $this->pdf->GetY();

        // Header row
        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->SetXY($col1X, $startY);
        $this->pdf->Cell($col1W, 4, 'DESTINATÁRIO DA OPERAÇÃO', 0, 0, 'L');
        $this->pdf->SetFont($this->font, 'B', 6);
        $this->pdf->SetXY($col2X, $startY);
        $this->pdf->Cell($col2W, 4, 'CNPJ / CPF / NIF', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $startY);
        $this->pdf->Cell($col3W, 4, 'Telefone', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 7);
        $this->pdf->SetXY($col2X, $startY + 4);
        $this->pdf->Cell($col2W, 4, $dest['doc'], 0, 0, 'L');
        $this->pdf->SetXY($col3X, $startY + 4);
        $this->pdf->Cell($col3W, 4, $dest['fone'], 0, 1, 'L');

        // Header row
        $row2Y = $this->pdf->GetY();
        $this->pdf->SetFont($this->font, 'B', 6);
        $this->pdf->SetXY($col1X, $row2Y);
        $this->pdf->Cell($col1W, 4, 'Nome / Nome Empresarial', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row2Y);
        $this->pdf->Cell($col3W, 4, 'E-mail', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 7);
        $this->pdf->SetXY($col1X, $row2Y + 4);
        $this->pdf->MultiCell($col1W + $col2W, 4, $dest['nome'], 0, 'L');

        $row3Y = max($this->pdf->GetY(), $row2Y + 4); // altura abaixo do multicell, que pode quebrar de linha

        $this->pdf->SetXY($col3X, $row2Y + 4);
        $this->pdf->Cell($col3W + $col4W, 4, $dest['email'], 0, 0, 'L');

        // Header row
        $this->pdf->SetFont($this->font, 'B', 6);
        $this->pdf->SetXY($col1X, $row3Y);
        $this->pdf->Cell($col1W, 4, 'Endereço', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row3Y);
        $this->pdf->Cell($col3W, 4, 'Município', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row3Y);
        $this->pdf->Cell($col4W, 4, 'Código IBGE / CEP', 0, 1, 'L');

        $complemento = !empty($dest['complemento']) ? ', ' . $dest['complemento'] : '';
        $endereco = $dest['logradouro'] . ', ' . $dest['numero'] . $complemento . ', ' . $dest['bairro'];
        $cidade = !empty($dest['municipio']) ? $dest['municipio'] . ' - ' . $dest['uf'] : '-';

        // Data row
        $this->pdf->SetFont($this->font, '', 7);
        $this->pdf->SetXY($col1X, $row3Y + 4);
        $this->pdf->MultiCell($col1W + $col2W, 4, $endereco, 0, 'L');

        $row4Y = max($this->pdf->GetY(), $row3Y + 4); // altura abaixo do multicell, que pode quebrar de linha

        $this->pdf->SetXY($col3X, $row3Y + 4);
        $this->pdf->Cell($col3W, 4, $cidade, 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row3Y + 4);
        $this->pdf->Cell($col4W, 4, $dest['ibgeMunicipio'] . ' / ' . $dest['cep'], 0, 1, 'L');

        $this->pdf->setY($row4Y); // após o final do endereco
        $this->pdf->Ln(1);
    }

    private function addIntermediario() {
        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->Cell(0, 0, 'INTERMEDIÁRIO DO SERVIÇO NÃO IDENTIFICADO NA NFS-e', 0, 1, 'C');

        $this->pdf->Ln(1);
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
        $this->pdf->SetFont($this->font, '', 7);

        $startY = $this->pdf->GetY();

        $serv = $this->data['servico'];

        $codTribNac = $this->formatCodTribNac($serv['codTribNac']) . ' - ' . $this->data['tribNac'];

        $codTribMun = $serv['codTribMun'];
        if (!empty($serv['descTribMun'])) {
            $codTribMun .= ' - ' . $serv['descTribMun'];
        }
        $codTribMun = $this->truncateTextToLines($codTribMun, $col1W - 2, 2);

        $localPrestacao = $this->data['localPrestacao'];
        if (!empty($this->data['localPrestacaoUf'])) {
            $localPrestacao .= ' - ' . $this->data['localPrestacaoUf'];
        }

        // Header row - Código de Tributação Nacional (linha inteira)
        $this->pdf->SetFont($this->font, 'B', 6);
        $this->pdf->SetXY($col1X, $startY);
        $this->pdf->Cell($col1W + $col2W + $col3W + $col4W, 4, 'Código de Tributação Nacional', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 7);
        $this->pdf->SetXY($col1X, $startY + 4);
        $this->pdf->MultiCell($col1W + $col2W + $col3W + $col4W, 4, $codTribNac, 0, 'L');

        $row2Y = max($this->pdf->GetY(), $startY + 4);

        // Header row - Código de Tributação Municipal / Local da Prestação / País da Prestação
        $this->pdf->SetFont($this->font, 'B', 6);
        $this->pdf->SetXY($col1X, $row2Y);
        $this->pdf->Cell($col1W, 4, 'Código de Tributação Municipal', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row2Y);
        $this->pdf->Cell($col2W, 4, 'Local da Prestação', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row2Y);
        $this->pdf->Cell($col3W, 4, 'País da Prestação', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 7);
        $this->pdf->SetXY($col1X, $row2Y + 4);
        $this->pdf->MultiCell($col1W, 4, $codTribMun, 0, 'L');
        $row3YCol1 = $this->pdf->GetY();

        $this->pdf->SetXY($col2X, $row2Y + 4);
        $this->pdf->Cell($col2W, 4, $localPrestacao, 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row2Y + 4);
        $this->pdf->Cell($col3W, 4, $this->paisIso($this->data['localPrestacaoPais']), 0, 1, 'L');

        $row3Y = max($row3YCol1, $row2Y + 4); // altura abaixo do multicell, que pode quebrar de linha

        // Código NBS / Descrição do Serviço
        $this->pdf->SetFont($this->font, 'B', 6);
        $this->pdf->SetXY($col1X, $row3Y);
        $this->pdf->Cell($col1W, 4, 'Código NBS', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row3Y);
        $this->pdf->Cell($col2W + $col3W + $col4W, 4, 'Descrição do Serviço', 0, 1, 'L');

        $this->pdf->SetFont($this->font, '', 7);
        $this->pdf->SetXY($col1X, $row3Y + 4);
        $nbs = $serv['nbs'];
        if (!empty($serv['descNbs'])) {
            $nbs .= ' - ' . $serv['descNbs'];
        }
        $this->pdf->MultiCell($col1W, 4, $nbs, 0, 'L');
        $row4YCol1 = $this->pdf->GetY();

        $this->pdf->SetXY($col2X, $row3Y + 4);
        $this->pdf->MultiCell($col2W + $col3W + $col4W, 4, $serv['descricao'], 0, 'L');
        $row4YCol2 = $this->pdf->GetY();

        $row4Y = max($row4YCol1, $row4YCol2, $row3Y + 4);

        $this->pdf->setY($row4Y);
        $this->pdf->Ln(1);
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

        if (empty($trib['existeTribMun'])) {
            $this->pdf->SetFont($this->font, 'B', 7);
            $this->pdf->Cell(0, 0, 'TRIBUTAÇÃO MUNICIPAL (ISSQN) - OPERAÇÃO NÃO SUJEITA AO ISSQN', 0, 1, 'C');
            $this->pdf->Ln(1);

            return;
        }

        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->Cell(0, 4, 'TRIBUTAÇÃO MUNICIPAL', 0, 1, 'L');
        $this->pdf->SetFont($this->font, '', 7);

        $startY = $this->pdf->GetY();

        // Header row
        $this->pdf->SetFont($this->font, 'B', 6);
        $this->pdf->SetXY($col1X, $startY);
        $this->pdf->Cell($col1W, 4, 'Tributação do ISSQN', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $startY);
        $this->pdf->Cell($col2W, 4, 'País Resultado da Prestação do Serviço', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $startY);
        $this->pdf->Cell($col3W, 4, 'Município de Incidência do ISSQN', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $startY);
        $this->pdf->Cell($col4W, 4, 'Regime Especial de Tributação', 0, 1, 'L');

        $localIncidencia = $this->data['localIncidencia'];
        if (!empty($this->data['localIncidenciaUf'])) {
            $localIncidencia .= ' - ' . $this->data['localIncidenciaUf'];
        }

        // Data row
        $this->pdf->SetFont($this->font, '', 7);
        $this->pdf->SetXY($col1X, $startY + 4);
        $this->pdf->Cell($col1W, 4, $this->tributacaoISSQN($trib['tipoTributacaoISSQN']), 0, 0, 'L');
        $this->pdf->SetXY($col2X, $startY + 4);
        $this->pdf->Cell($col2W, 4, $this->paisIso($this->data['localIncidenciaPais']), 0, 0, 'L');
        $this->pdf->SetXY($col3X, $startY + 4);
        $this->pdf->Cell($col3W, 4, $localIncidencia, 0, 0, 'L');
        $this->pdf->SetXY($col4X, $startY + 4);
        $this->pdf->Cell($col4W, 4, $this->regimeEspecialTributacao($trib['regimeEspecialTributacao']), 0, 1, 'L');

        // Header row
        $row2Y = $this->pdf->GetY();
        $this->pdf->SetFont($this->font, 'B', 6);
        $this->pdf->SetXY($col1X, $row2Y);
        $this->pdf->Cell($col1W, 4, 'Tipo de Imunidade', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row2Y);
        $this->pdf->Cell($col2W, 4, 'Suspensão da Exigibilidade do ISSQN', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row2Y);
        $this->pdf->Cell($col3W, 4, 'Número Processo Suspensão', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row2Y);
        $this->pdf->Cell($col4W, 4, 'Benefício Municipal', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 7);
        $this->pdf->SetXY($col1X, $row2Y + 4);
        $this->pdf->Cell($col1W, 4, $this->tipoImunidade($trib['tipoImunidade']), 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row2Y + 4);
        $this->pdf->Cell($col2W, 4, $this->suspensaoExigibilidadeISSQN($trib['tipoSuspensao']), 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row2Y + 4);
        $this->pdf->Cell($col3W, 4, $trib['nProcessoSuspensao'] ?: '-', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row2Y + 4);
        $this->pdf->Cell($col4W, 4, $this->tipoBeneficioMunicipal($trib['tipoBeneficioMunicipal']), 0, 1, 'L');

        // Header row
        $row3Y = $this->pdf->GetY();
        $this->pdf->SetFont($this->font, 'B', 6);
        $this->pdf->SetXY($col1X, $row3Y);
        $this->pdf->Cell($col1W, 4, 'Valor do Serviço', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row3Y);
        $this->pdf->Cell($col2W, 4, 'Desconto Incondicionado', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row3Y);
        $this->pdf->Cell($col3W, 4, 'Total Deduções/Reduções', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row3Y);
        $this->pdf->Cell($col4W, 4, 'Cálculo do BM', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 7);
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
        $this->pdf->SetFont($this->font, 'B', 6);
        $this->pdf->SetXY($col1X, $row4Y);
        $this->pdf->Cell($col1W, 4, 'BC ISSQN', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row4Y);
        $this->pdf->Cell($col2W, 4, 'Alíquota Aplicada', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row4Y);
        $this->pdf->Cell($col3W, 4, 'Retenção do ISSQN', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row4Y);
        $this->pdf->Cell($col4W, 4, 'ISSQN Apurado', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 7);
        $this->pdf->SetXY($col1X, $row4Y + 4);
        $this->pdf->Cell($col1W, 4, $val['baseCalculoISSQN'], 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row4Y + 4);
        $this->pdf->Cell($col2W, 4, $trib['percentualAliquotaAplicadaISSQN'], 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row4Y + 4);
        $this->pdf->Cell($col3W, 4, $this->tipoRetencaoISSQN($trib['tipoRetencaoISSQN']), 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row4Y + 4);
        $this->pdf->Cell($col4W, 4, $val['ISSQN'], 0, 1, 'L');
        $this->pdf->Ln(1);
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
        $this->pdf->SetFont($this->font, '', 7);

        // Header row
        $row5Y = $this->pdf->GetY();
        $this->pdf->SetFont($this->font, 'B', 6);
        $this->pdf->SetXY($col1X, $row5Y);
        $this->pdf->Cell($col1W, 4, 'IRRF', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row5Y);
        $this->pdf->Cell($col2W, 4, 'CP', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row5Y);
        $this->pdf->Cell($col3W, 4, 'CSLL', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row5Y);
        $this->pdf->Cell($col4W, 4, '', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 7);
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
        $this->pdf->SetFont($this->font, 'B', 6);
        $this->pdf->SetXY($col1X, $row6Y);
        $this->pdf->Cell($col1W, 4, 'PIS - Débito apuração própria', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row6Y);
        $this->pdf->Cell($col2W, 4, 'COFINS - Débito apuração própria', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row6Y);
        $this->pdf->Cell($col3W, 4, 'Descrição Contrib. Sociais - Retidas', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row6Y);
        $this->pdf->Cell($col4W, 4, 'TOTAL TRIBUTAÇÃO FEDERAL', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 7);
        $this->pdf->SetXY($col1X, $row6Y + 4);
        $this->pdf->Cell($col1W, 4, $val['PIS'], 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row6Y + 4);
        $this->pdf->Cell($col2W, 4, $val['COFINS'], 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row6Y + 4);
        $this->pdf->Cell($col3W, 4, $this->retencaoPisCofins($trib['tipoRetencaoPisCofins']), 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row6Y + 4);
        $this->pdf->Cell($col4W, 4, $val['totalTributosFederais'], 0, 1, 'L');
        $this->pdf->Ln(1);
    }

    private function addTributacaoIbsCbs() {
        $col1X = $this->margin;
        $col2X = 47;
        $col3X = 97;
        $col4X = 147;
        $col1W = 45;
        $col2W = 50;
        $col3W = 50;
        $col4W = 50;

        $ibsCbs = $this->data['ibsCbs'];

        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->Cell(0, 4, 'TRIBUTAÇÃO IBS/CBS', 0, 1, 'L');
        $this->pdf->SetFont($this->font, '', 7);

        $localidadeIncid = $ibsCbs['localidadeIncid'];
        if (!empty($ibsCbs['localidadeIncidUf'])) {
            $localidadeIncid .= ' - ' . $ibsCbs['localidadeIncidUf'];
        }

        $indicadorOperacao = $ibsCbs['cIndOp'] . ' / ' . $ibsCbs['localidadeIncidIbge'] . ' / ' . $localidadeIncid;
        $reducaoAliquota = $ibsCbs['reducaoAliqIbsUf'] . ' / ' . $ibsCbs['reducaoAliqIbsMun'] . ' / ' . $ibsCbs['reducaoAliqCbs'];
        $aliquotaIbsUfMun = $ibsCbs['aliqIbsUf'] . ' / ' . $ibsCbs['aliqIbsMun'];

        // Header row
        $row1Y = $this->pdf->GetY();
        $this->pdf->SetFont($this->font, 'B', 6);
        $this->pdf->SetXY($col1X, $row1Y);
        $this->pdf->Cell($col1W, 4, 'CST / Classificação Tributária', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row1Y);
        $this->pdf->Cell($col2W + $col3W, 4, 'Indicador de Operação / Código IBGE / Município / UF de Incidência', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row1Y);
        $this->pdf->Cell($col4W, 4, 'Exclusões/Reduções da BC', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 7);
        $this->pdf->SetXY($col1X, $row1Y + 4);
        $this->pdf->Cell($col1W, 4, $ibsCbs['cst'] . ' / ' . $ibsCbs['classTrib'], 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row1Y + 4);
        $this->pdf->Cell($col2W + $col3W, 4, $indicadorOperacao, 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row1Y + 4);
        $this->pdf->Cell($col4W, 4, $ibsCbs['exclusoesReducoes'], 0, 1, 'L');

        // Header row
        $row2Y = $this->pdf->GetY();
        $this->pdf->SetFont($this->font, 'B', 6);
        $this->pdf->SetXY($col1X, $row2Y);
        $this->pdf->Cell($col1W, 4, 'BC Após Exclusões/Reduções', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row2Y);
        $this->pdf->Cell($col2W, 4, 'Red. Alíquota IBS (UF/Mun) / CBS', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row2Y);
        $this->pdf->Cell($col3W, 4, 'Alíquota IBS (UF / Mun)', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row2Y);
        $this->pdf->Cell($col4W, 4, 'Alíquota Efetiva IBS Municipal', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 7);
        $this->pdf->SetXY($col1X, $row2Y + 4);
        $this->pdf->Cell($col1W, 4, $ibsCbs['baseCalculo'], 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row2Y + 4);
        $this->pdf->Cell($col2W, 4, $reducaoAliquota, 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row2Y + 4);
        $this->pdf->Cell($col3W, 4, $aliquotaIbsUfMun, 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row2Y + 4);
        $this->pdf->Cell($col4W, 4, $ibsCbs['aliqEfetIbsMun'], 0, 1, 'L');

        // Header row
        $row3Y = $this->pdf->GetY();
        $this->pdf->SetFont($this->font, 'B', 6);
        $this->pdf->SetXY($col1X, $row3Y);
        $this->pdf->Cell($col1W, 4, 'Valor Apurado IBS Municipal', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row3Y);
        $this->pdf->Cell($col2W, 4, 'Alíquota Efetiva IBS Estadual', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row3Y);
        $this->pdf->Cell($col3W, 4, 'Valor Apurado IBS Estadual', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row3Y);
        $this->pdf->Cell($col4W, 4, 'Valor Total Apurado do IBS', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 7);
        $this->pdf->SetXY($col1X, $row3Y + 4);
        $this->pdf->Cell($col1W, 4, $ibsCbs['valorIbsMun'], 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row3Y + 4);
        $this->pdf->Cell($col2W, 4, $ibsCbs['aliqEfetIbsUf'], 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row3Y + 4);
        $this->pdf->Cell($col3W, 4, $ibsCbs['valorIbsUf'], 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row3Y + 4);
        $this->pdf->Cell($col4W, 4, $ibsCbs['valorTotalIbs'], 0, 1, 'L');

        // Header row
        $row4Y = $this->pdf->GetY();
        $this->pdf->SetFont($this->font, 'B', 6);
        $this->pdf->SetXY($col1X, $row4Y);
        $this->pdf->Cell($col1W, 4, 'Alíquota da CBS', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row4Y);
        $this->pdf->Cell($col2W, 4, 'Alíquota Efetiva da CBS', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row4Y);
        $this->pdf->Cell($col3W, 4, 'Valor Total Apurado da CBS', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 7);
        $this->pdf->SetXY($col1X, $row4Y + 4);
        $this->pdf->Cell($col1W, 4, $ibsCbs['aliqCbs'], 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row4Y + 4);
        $this->pdf->Cell($col2W, 4, $ibsCbs['aliqEfetCbs'], 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row4Y + 4);
        $this->pdf->Cell($col3W, 4, $ibsCbs['valorTotalCbs'], 0, 1, 'L');
        $this->pdf->Ln(1);
    }

    private function addValores() {
        $col1X = $this->margin;
        $col2X = 47;
        $col3X = 97;
        $col4X = 147;
        $col1W = 45;
        $col2W = 50;
        $col3W = 50;
        $col4W = 50;

        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->Cell(0, 4, 'VALOR TOTAL DA NFS-E', 0, 1, 'L');
        $this->pdf->SetFont($this->font, '', 7);

        $val = $this->data['valores'];
        $ibsCbs = $this->data['ibsCbs'];
        $startY = $this->pdf->GetY();

        // Header row
        $this->pdf->SetFont($this->font, 'B', 6);
        $this->pdf->SetXY($col1X, $startY);
        $this->pdf->Cell($col1W, 4, 'Valor da Operação / Serviço', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $startY);
        $this->pdf->Cell($col2W, 4, 'Desconto Incondicionado', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $startY);
        $this->pdf->Cell($col3W, 4, 'Desconto Condicionado', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 7);
        $this->pdf->SetXY($col1X, $startY + 4);
        $this->pdf->Cell($col1W, 4, $val['servico'], 0, 0, 'L');
        $this->pdf->SetXY($col2X, $startY + 4);
        $this->pdf->Cell($col2W, 4, $val['descontoIncondicionado'], 0, 0, 'L');
        $this->pdf->SetXY($col3X, $startY + 4);
        $this->pdf->Cell($col3W, 4, $val['descontoCondicionado'], 0, 1, 'L');

        // Header row
        $row2Y = $this->pdf->GetY();
        $this->pdf->SetFont($this->font, 'B', 6);
        $this->pdf->SetXY($col1X, $row2Y);
        $this->pdf->Cell($col1W, 4, 'Total das Retenções Federais', 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row2Y);
        $this->pdf->Cell($col2W, 4, 'Valor Líquido da NFS-e', 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row2Y);
        $this->pdf->Cell($col3W, 4, 'Total do IBS/CBS', 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row2Y);
        $this->pdf->Cell($col4W, 4, 'Valor Líquido da NFS-e + IBS/CBS', 0, 1, 'L');

        // Data row
        $this->pdf->SetFont($this->font, '', 7);
        $this->pdf->SetXY($col1X, $row2Y + 4);
        $this->pdf->Cell($col1W, 4, $val['totalRetencoes'], 0, 0, 'L');
        $this->pdf->SetXY($col2X, $row2Y + 4);
        $this->pdf->Cell($col2W, 4, $val['liquido'], 0, 0, 'L');
        $this->pdf->SetXY($col3X, $row2Y + 4);
        $this->pdf->Cell($col3W, 4, $ibsCbs['valorTotalIbsCbs'], 0, 0, 'L');
        $this->pdf->SetXY($col4X, $row2Y + 4);
        $this->pdf->Cell($col4W, 4, $ibsCbs['liquidoComIbsCbs'], 0, 1, 'L');

        $this->pdf->Ln(1);
    }

    private function addInformacoesComplementares() {
        // Header row
        $this->pdf->SetFont($this->font, 'B', 7);
        $this->pdf->Cell(0, 4, 'INFORMAÇÕES COMPLEMENTARES', 0, 1, 'L');
        $this->pdf->SetFont($this->font, '', 7);

        $info = $this->data['infoComplementares'];

        // Totais Aproximados dos Tributos é obrigatório; o restante só aparece quando preenchido
        $texto = $info['texto'] !== '' ? $info['texto'] . ' | ' . $info['totaisTributos'] : $info['totaisTributos'];

        $this->pdf->MultiCell(0, 0, $texto, 0, 'L');
    }


    private function enderecoTomador(array $toma): array {
        $cidade = $toma['municipio'] ?? null;

        if ($cidade) {
            $cidade = "$toma[municipio] - $toma[uf]";
        } elseif (!empty($toma['ibgeMunicipio'])) {
            $cidade = 'Município IBGE ' . $toma['ibgeMunicipio'];
        } else {
            $cidade = '-';
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
    private function emitenteNFSe($cTpEmit): string {
        // NFSe/infNFSe/DPS/infDPS/tpEmit
        // 1 - Prestador; 2 - Tomador; 3 - Intermediário;
        $tpEmit = ['1' => 'Prestador', '2' => 'Tomador', '3' => 'Intermediário'];

        return $tpEmit[$cTpEmit] ?? ($cTpEmit !== '' ? "tpEmit $cTpEmit" : '-');
    }

    private function situacaoNFSe($cStat): string {
        // NFSe/infNFSe/cStat
        $cStat = (string) $cStat;
        $situacoes = [
            '100' => 'Autorizado o uso da NF-e',
            '101' => 'Cancelamento homologado',
            '102' => 'Inutilização homologada',
            '110' => 'Uso denegado',
            '150' => 'Autorizado fora do prazo',
            '301' => 'Uso denegado',
            '302' => 'Uso denegado',
        ];

        return $situacoes[$cStat] ?? ($cStat !== '' ? "cStat $cStat" : '-');
    }

    private function finalidadeNFSe($cFinNFSe): string {
        // NFSe/infNFSe/DPS/infDPS/IBSCBS/finNFSe
        $finNFSe = ['0' => 'NFS-e regular'];

        return $finNFSe[$cFinNFSe] ?? ($cFinNFSe !== '' ? "finNFSe $cFinNFSe" : '-');
    }

    private function ambienteGerador($cAmbGer): string {
        // NFSe/infNFSe/ambGer — Ambiente Gerador da NFS-e (padrão ABRASF/Sistema Nacional NFS-e)
        $ambGer = ['1' => 'Sistema do Contribuinte', '2' => 'Sistema Nacional'];

        return $ambGer[$cAmbGer] ?? ($cAmbGer !== '' ? "ambGer $cAmbGer" : '-');
    }

    private function tipoAmbiente($cTpAmb): string {
        // NFSe/infNFSe/DPS/infDPS/tpAmb
        $tpAmb = ['1' => 'Produção', '2' => 'Homologação'];

        return $tpAmb[$cTpAmb] ?? ($cTpAmb !== '' ? "tpAmb $cTpAmb" : '-');
    }

    private function paisIso($cPais): string {
        // Tabela de Países (Receita Federal) — só o código do Brasil está confirmado
        $paises = ['1058' => 'BR'];

        return $paises[$cPais] ?? ($cPais !== '' ? $cPais : '-');
    }

    private function tipoBeneficioMunicipal($cTpBM): string {
        // NFSe/infNFSe/valores/tpBM — leiaute prevê 4 opções (não confirmadas); mostra o código bruto
        return $cTpBM !== '' ? $cTpBM : '-';
    }

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
        // 0 - PIS/COFINS/CSLL Não Retidos
        // 1 - PIS/COFINS Retido
        // 2 - PIS/COFINS Não Retido
        // 3 - PIS/COFINS/CSLL Retidos
        // 4 - PIS/COFINS Retidos, CSLL Não Retido
        // 5 - PIS Retido, COFINS/CSLL Não Retido
        // 6 - COFINS Retido, PIS/CSLL Não Retido
        // 7 - PIS Não Retido, COFINS/CSLL Retidos
        // 8 - PIS/COFINS Não Retidos, CSLL Retido
        // 9 - COFINS Não Retido, PIS/CSLL Retidos

        $tpRetPisCofins = '-';
        if (is_numeric($cTpRetPisCofins)) {
            if ($cTpRetPisCofins == 0) {
                $tpRetPisCofins = 'PIS/COFINS/CSLL Não Retidos';
            } elseif ($cTpRetPisCofins == 1) {
                $tpRetPisCofins = 'PIS/COFINS Retido';
            } elseif ($cTpRetPisCofins == 2) {
                $tpRetPisCofins = 'PIS/COFINS Não Retido';
            } elseif ($cTpRetPisCofins == 3) {
                $tpRetPisCofins = 'PIS/COFINS/CSLL Retidos';
            } elseif ($cTpRetPisCofins == 4) {
                $tpRetPisCofins = 'PIS/COFINS Retidos, CSLL Não Retido';
            } elseif ($cTpRetPisCofins == 5) {
                $tpRetPisCofins = 'PIS Retido, COFINS/CSLL Não Retido';
            } elseif ($cTpRetPisCofins == 6) {
                $tpRetPisCofins = 'COFINS Retido, PIS/CSLL Não Retido';
            } elseif ($cTpRetPisCofins == 7) {
                $tpRetPisCofins = 'PIS Não Retido, COFINS/CSLL Retidos';
            } elseif ($cTpRetPisCofins == 8) {
                $tpRetPisCofins = 'PIS/COFINS Não Retidos, CSLL Retido';
            } elseif ($cTpRetPisCofins == 9) {
                $tpRetPisCofins = 'COFINS Não Retido, PIS/CSLL Retidos';
            }
        }

        return $tpRetPisCofins;
    }
}
