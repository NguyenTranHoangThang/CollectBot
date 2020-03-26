<?php

namespace App\Controller;

use App\Entity\Report;
use DateTime;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use mysqli;
use PHPExcel_IOFactory;
use PHPExcel_Reader_HTML;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TestController extends AbstractController
{
    /**
     * @Route("/test", name="test")
     */
    public function index()
    {
        $host = 'docker.for.win.localhost:8888';

        $capabilities = DesiredCapabilities::chrome();

        $driver = RemoteWebDriver::create($host, $capabilities);
        $driver->get('https://frenchies.zenoti.com/SignIn.aspx');
        $driver
            ->findElement(WebDriverBy::name('txtUserName'))
            ->sendKeys('access@ceterus.com');
        $driver->findElement(WebDriverBy::name('txtPassword'))
            ->sendKeys('Porter16!')
            ->submit();
        $driver->wait()->until(
            WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::xpath('//*[@id="accordion"]/li[1]/div'))
        );
        $btnReport = $driver->findElement(
            WebDriverBy::xpath('//*[@id="accordion"]/li[1]/div')
        )->click();
        $tagAccount = $driver->findElement(
            WebDriverBy::xpath('//*[@id="MainContent_HyperLink9"]')
        )->click();
        $driver->wait()->until(
            WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::xpath('//*[@id="ctl00_ctl00_MainContent_PageContent_ddlReportType"]/table/tbody/tr/td[1]'))
        );
        $selectReport = $driver->findElement(
            WebDriverBy::xpath('//*[@id="ctl00_ctl00_MainContent_PageContent_ddlReportType"]/table/tbody/tr/td[1]')
        )->click();
        $driver->wait()->until(
            WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::xpath('//*[@id="ctl00_ctl00_MainContent_PageContent_ddlReportType_DropDown"]/div/ul/li[16]'))
        );
        $driver->findElement(
            WebDriverBy::xpath('//*[text()=\'Register Summary\']')
        )->click();
        $selectTimePeriod = $driver->findElement(
            WebDriverBy::xpath('//*[@id="MainContent_PageContent_txtTimePeriod"]')
        )->click();
        $driver->findElement(
//            WebDriverBy::cssSelector('li[data-range-key="Today"]')
            WebDriverBy::xpath('/html/body/div[3]/div[1]/ul/li[text()=\'Today\']')
        )->click();
        $btnRefresh = $driver->findElement(
            WebDriverBy::xpath('//*[@id="MainContent_PageContent_refreshbtn"]')
        )->click();
        $driver->wait()->until(
            WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::xpath('//*[@id="idExportSummary"]'))
        );
        $driver->findElement(
            WebDriverBy::xpath('//*[@id="idExportSummary"]')
        )->click();

        $data = $this->extractData();
//        dd($data);
        $this->storeInDBbySql($data);
        return new Response('done');
    }

    public function extractData()
    {
        $objPHPExcel = PHPExcel_IOFactory::createReaderForFile("RegisterSummary.xls");
        $report = @$objPHPExcel->load('RegisterSummary.xls')->getActiveSheet();

        //allCells
//        $data = [];
//        foreach ($report->getCellCollection() as $cellNumber){
//            $data[$cellNumber] = $report->getCell($cellNumber)->getValue();
//        }
//        dd($data);
        //allCells
        $data = [];
        //-----Sales

        for ($i = 4; $i <= 13; $i += 3) {
            $temp = [];
            for ($j = 0; $j <= 2; $j++) {
                $temp[] = [$report->getCell("B" . ($i + $j))->getValue(), $report->getCell("C" . ($i + $j))->getValue()];
            }
            $data[$report->getCell('A' . $i)->getValue()] = $temp;
        }
        $prepaidCardTemp = [
            [$report->getCell("B18")->getValue(), $report->getCell("C18")->getValue()],
            [$report->getCell("B19")->getValue(), $report->getCell("C19")->getValue()],
            [$report->getCell("B20")->getValue(), $report->getCell("C20")->getValue()],
            [$report->getCell("B22")->getValue(), $report->getCell("C22")->getValue()],
            [$report->getCell("B23")->getValue(), $report->getCell("C23")->getValue()]
        ];
        $data[$report->getCell('A18')->getValue()] = $prepaidCardTemp;
        $taxesTemp = [
            [$report->getCell("B26")->getValue(), $report->getCell("C26")->getValue()],
            [$report->getCell("B27")->getValue(), $report->getCell("C27")->getValue()],
            [$report->getCell("B38")->getValue(), $report->getCell("C38")->getValue()],
        ];
        $data[$report->getCell('A26')->getValue()] = $taxesTemp;
        //-----EndSales
        return $data;
    }
    public function storeInDB($data){
        $entityManager = $this->getDoctrine()->getManager();
        $report = new Report();
        $report->setContent($data);
        $today = new DateTime();
        $report->setDate($today);
        $entityManager->persist($report);
        $entityManager->flush();
    }
    public function storeInDBbySql($data){
        $conn = $this->getDoctrine()->getManager()->getConnection();
        $data = json_encode($data);
        $today = date('Y-m-d');
        $sql = "
        INSERT INTO report (content,date) VALUES ('$data','$today')
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    }
}
