# NFS-e Nacional PDF Generator


[![Latest Version on Packagist](https://img.shields.io/packagist/v/jaimearaujo18/nfse-nacional-pdf.svg?style=flat-square)](https://packagist.org/packages/jaimearaujo18/nfse-nacional-pdf)
[![Total Downloads](https://img.shields.io/packagist/dt/jaimearaujo18/nfse-nacional-pdf.svg?style=flat-square)](https://packagist.org/packages/jaimearaujo18/nfse-nacional-pdf)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)

Gerador de PDF para Documento Auxiliar da NFS-e (DANFSe) a partir de arquivos XML da NFS-e Nacional.

## Descrição

Este projeto converte arquivos XML de Nota Fiscal de Serviços Eletrônica (NFS-e) no formato nacional em documentos 
PDF formatados (DANFSe - Documento Auxiliar da NFS-e). Soçução independente que não tem qualquer relação com o 
oficial.

## Requisitos

- PHP >= 7.4
- Composer

## Exemplo de uso

```php
$title = 'NFSe-00123456789.pdf';
$xml_path = 'path-to-nfse-xml-file.xml';

if (!file_exists($xml_path)) {
    die("Arquivo XML da NFS-e não encontrado para geração do DANFSe.");
}

$generator = new NfsePdfGenerator('Sistema');
$generator->parseXml($xml_path);
$generator->setTitle($title);

// exibição dos dados da prefeitura
// departamento, fone, e-mail e brasão
// Caso não seja definido, não serão exibidos dados "extras" da preitura,
// somente "Prefeitura Municipal de [Local de emissão]" (NFSe/infNFSe/xLocEmi)
$generator->setMunicipality([
    'department' => 'Secretaria Municipal da Fazenda',
    'phone' => '(11) 4002-8922',
    'email' => 'email@cidade.emissao.com',
    'image' => 'path-to-municipality-coat-of-arms.png' // local file path
]);

// O nome da cidade e sigla UF do tomador não estão disponíveis no XML
// Utilize esses dois métodos para buscar o código IBGE da cidade e definir o nome/UF
// Caso não seja definido um nome/uf externamente, será exibido somente o nome da
// cidade de prestação do serviço (NFSe/infNFSe/xLocPrestacao)
$ibge_cidade_tomador = $generator->getTomadorCidadeIBGE();

// buscar nome da cidade e sua UF, de algum local publico ou banco de dados
// $cidade_tomador = buscar_cidade_uf_by_cidade_ibge($ibge_cidade_tomador);
$cidade_tomador = (object) [
    'nome' => 'Sapiranga',
    'uf' => 'RS'
];

if ($cidade_tomador) {
    $generator->setTomadorCidadeUF($cidade_tomador->nome, $cidade_tomador->uf);
}

// adiciona todo o conteúdo do XML no PDF em memória
$pdf = $generator->generate();

// Opções de output: (TCPDF)
// 'I' -> Send PDF to the standard output
// 'D' -> download PDF as file
// 'F' -> save PDF to a local file
$pdf->Output($title, 'I');
```
