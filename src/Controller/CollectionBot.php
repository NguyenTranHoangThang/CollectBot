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
use ExcelUtilities;

class CollectionBot extends AbstractController
{
    private const HOST = 'docker.for.win.localhost:8888';
    private const LOGINURL = 'https://frenchies.zenoti.com/SignIn.aspx';
    private const USERNAME = 'access@ceterus.com';
    private const PASSWORD = 'Porter16!';

    /**
     * @Route("/collect", name="collect")
     */
    public function collect()
    {
        $capabilities = DesiredCapabilities::chrome();
        $driver = RemoteWebDriver::create(self::HOST, $capabilities);
        $this->login($driver);
        $this->navigateReportPage($driver);
        $this->selectReportType($driver);
        $this->downloadReport($driver);

        $data = $this->extractData();
        $this->storeInDBbySql($data);
        return new Response('done');
    }

    public function login($driver)
    {
        $driver->get(self::LOGINURL);
        $driver
            ->findElement(WebDriverBy::name('txtUserName'))
            ->sendKeys(self::USERNAME);
        $driver->findElement(WebDriverBy::name('txtPassword'))
            ->sendKeys(self::PASSWORD)
            ->submit();
    }

    public function navigateReportPage($driver)
    {
        $driver->wait()->until(
            WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::xpath('//*[@id="accordion"]/li[1]/div'))
        );
        $btnReport = $driver->findElement(
            WebDriverBy::xpath('//ul[@id="accordion"]/li[1]/div')
        )->click();
        $tagAccount = $driver->findElement(
            WebDriverBy::xpath('//a[@id="MainContent_HyperLink9"]')
        )->click();
    }

    public function selectReportType(RemoteWebDriver $driver)
    {
        $driver->wait()->until(
            WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::xpath('//input[@id="ctl00_ctl00_MainContent_PageContent_ddlReportType_Input"]'))
        );
        $selectReport = $driver->findElement(
            WebDriverBy::xpath('//input[@id="ctl00_ctl00_MainContent_PageContent_ddlReportType_Input"]')
        )->click();
        $driver->wait()->until(
            WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::xpath('//li[@class="rcbItem" and contains(text(),"Register Summary")]'))
        );
        $driver->findElement(
            WebDriverBy::xpath('//li[contains(text(),"Register Summary")]')
        )->click();
        $selectTimePeriod = $driver->findElement(
            WebDriverBy::xpath('//input[@id="MainContent_PageContent_txtTimePeriod"]')
        )->click();
        $driver->findElement(
//            WebDriverBy::cssSelector('li[data-range-key="Today"]')
            WebDriverBy::xpath('//li[text()="Today"]')
        )->click();
        $btnRefresh = $driver->findElement(
            WebDriverBy::xpath('//input[@name="ctl00$ctl00$MainContent$PageContent$refreshbtn"]')
        )->click();
    }

    public function downloadReport(RemoteWebDriver $driver)
    {
        $driver->wait()->until(
            WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::xpath('//img[@id="idExportSummary"]'))
        );
        $driver->findElement(
            WebDriverBy::xpath('//img[@id="idExportSummary"]')
        )->click();
    }

    public function extractData()
    {
        $objPHPExcel = PHPExcel_IOFactory::createReaderForFile("RegisterSummary.xls");
        $report = @$objPHPExcel->load('RegisterSummary.xls');
        $worksheetArrayData = [];
        $allSheets = $report->getAllSheets();
        foreach ($allSheets as $sheet) {
            $worksheetArrayData[$sheet->getTitle()] = $sheet->toArray(null, true, false, false);
        }
        return $worksheetArrayData;
    }

    public function storeInDB($data)
    {
        $entityManager = $this->getDoctrine()->getManager();
        $report = new Report();
        $report->setContent($data);
        $today = new DateTime();
        $report->setDate($today);
        $entityManager->persist($report);
        $entityManager->flush();
    }

    public function storeInDBbySql($data)
    {
        $conn = $this->getDoctrine()->getManager()->getConnection();
        $data = json_encode($data);
        $data = str_replace('\\n', '\\\n', $data);
        $today = date('Y-m-d');
        $sql = "
        INSERT INTO report (content,date) VALUES ('$data','$today')
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    }
}
