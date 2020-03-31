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
        $data = [];
        $prepareJson = [
            ['D35', 'E35', false, false],
            ['C69', 'E69', true, false],
            ['C72', 'E72', true, false],
            ['A7', 'C7', false, true],
            ['A57', 'C57', false, true],
            ['A60', 'C60', false, true],
            ['A63', 'C63', false, true],
            ['D79', 'E79', true, false],
            ['D78', 'E78', true, false],
        ];
        foreach ($prepareJson as $account) {
            $data[$rawData[$account[0]]] = [
                "amount" => $rawData[$account[1]],
                "debit" => $account[2],
                "credit" => $account[3],
            ];
        }
        $sumDebit = 0;
        $sumCredit = 0;
        foreach ($data as $account) {
            if ($account["debit"] == true) {
                $sumDebit += $account["amount"];
            }
            if ($account["credit"] == true) {
                $sumCredit += $account["amount"];
            }
        }
        $diff = $sumDebit - $sumCredit;
        $data["Sales - Service"]['amount'] = $diff;
        $data["Sales - Service"]['debit'] = $diff < 0 ? true : false;
        $data["Sales - Service"]['credit'] = $diff > 0 ? true : false;
        return $data;
    }
}
