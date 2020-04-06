<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class ProcessBotController extends AbstractController
{
    /**
     * @Route("/process", name="process")
     */
    public function process()
    {
        $rawData = $this->getDataFromDB();
        $rawData = (array)json_decode($rawData[0]["content"]);
        $data = $this->processRawData($rawData);
        return $this->render('process_bot/index.html.twig', [
            'data' => $data,
        ]);
    }

    public function getDataFromDB()
    {
        $today = date('Y-m-d');
        $conn = $this->getDoctrine()->getManager()->getConnection();
        $sql = "SELECT * FROM report where date = '$today'";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }


    public function processRawData(array $rawData)
    {
        $mappingJson = json_decode(file_get_contents('../src/Config/DataDefinition.json'));
        $transformData = $seachRow = array_map(function ($array) {
            return implode('@', $array);
        }, $rawData["Worksheet"]);
        $dateResults = preg_grep('/\d{1,2}\/\d{1,2}\/\d{4}/', $transformData);
        preg_match('/\d{1,2}\/\d{1,2}\/\d{4}/', $dateResults[0], $date);

        $data = [];
        $i = 0;
        foreach ($mappingJson as $mapRow) {
            foreach ($transformData as $key => $dataRow) {
                preg_match($mapRow->searchPattern, $dataRow, $match);
                if (sizeof($match) > 0) {
                    $i += 1;
                    if ($i == $mapRow->occur) {
                        $foundRow = $rawData["Worksheet"][$key];
                        $value = str_replace(",", ".", $foundRow[$mapRow->valueColumn]);
                        $value = preg_replace('/\.(?=.*\.)/', '', $value);
                        $data[] = [
                            "account" => $foundRow[$mapRow->descriptionColumn],
                            "value" => (float)$value,
                            "type" => $mapRow->type
                        ];
                    }
                }
            }
            $i = 0;
        }
        $data = $this->recaculateGross($data);
        $sumDebit = 0;
        $sumCredit = 0;
        foreach ($data as $account) {
            if ($account["type"] == "debit") {
                $sumDebit += $account["value"];
            }
            if ($account["type"] == "credit") {
                $sumCredit += $account["value"];
            }
        }
        $diff = $sumDebit - $sumCredit;
        $data["Sales - Service"]['account'] = 'Sales - Service';
        $data["Sales - Service"]['value'] = $diff;
        $data["Sales - Service"]['type'] = $diff < 0 ? "debit" : "credit";
        $total = $sumDebit > $sumCredit ? (float)$sumDebit : (float)$sumCredit;
        return [$data, $total, $date[0]];
    }

    public function recaculateGross(array $data)
    {
        $feeValue = 0;
        foreach ($data as $account) {
            if (preg_match('/.*Fees.*/', $account["account"], $match)) {
                $feeValue = $account["value"];
                foreach ($data as $k => $acc) {
                    if (preg_match('/.*Gross.*/', $acc["account"], $match)) {
                        $data[$k]["value"] -= $feeValue;
                    }
                }
            }
        }
        return $data;
    }
}
