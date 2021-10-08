<?php

namespace App\Controller;

use App\Entity\Currency;
use App\Entity\Rate;
use Carbon\Carbon;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use App\Repository\CurrencyRepository;

/**
 * Class HomeController
 *
 * @package App\Controller
 */
class HomeController extends AbstractController
{
    /**
     * @Route("/", name="")
     * @Template
     */
    public function index(CurrencyRepository $repo): array
    {
        return [
            'currencies' => $repo->findAll()
        ];
    }

    /**
     * @Route("/convert", name="convert")
     * @Template
     */
    public function convert(CurrencyRepository $repo): Response
    {
        if (date('H') > 10 || date('H') < 11) {
            $this->updateCurrencyRates();
        }

        $currencies = $repo->findAll();

        return $this->render('home/converter.html.twig', [
            'currencies' => $currencies
        ]);
    }

    /**
     * @Route("/calculate", methods={"GET"})
     */
    public function calculate(Request $request): Response
    {
        $from = $request->query->get('from');
        $to = $request->query->get('to');

        $fromRate = $this->getCurrRate($from);
        $toRate = $this->getCurrRate($to);

        return new Response(json_encode(['from' => $fromRate, 'to' => $toRate]));
    }

    private function getCurrRate($currCode)
    {
        $rateRepo = $this->getDoctrine()->getRepository(Rate::class);
        $currRepo = $this->getDoctrine()->getRepository(Currency::class);
        $currId = $currCode === 'EUR' ? 0 : $currRepo->findOneBy(['code' => $currCode])->id;

        return $currId ? $rateRepo->findOneBy(['currency_id' => $currId])->rate : 1;
    }

    private function updateCurrencyRates()
    {
        $crawler = file_get_contents('https://www.ecb.europa.eu/stats/policy_and_exchange_rates/euro_reference_exchange_rates/html/index.en.html');
        $crawler = new Crawler($crawler);
        $rates = $crawler->filter('tbody > tr > td > a > span.rate');
        $currencies = $crawler->filter('tbody > tr > td.currency');

        $entityManager = $this->getDoctrine()->getManager();

        foreach ($rates as $key => $item) {
            $currency = $currencies->getNode($key)->textContent;
            $rate = $item->textContent;

            $repository = $this->getDoctrine()->getRepository(Currency::class);
            $currencyId = $repository->findOneBy(['code' => $currency])->id;

            if ($currencyId) {
                $product = new Rate();
                $product->setCurrencyId($currencyId);
                $product->setRate($rate);
                $product->setDate(Carbon::now());

                $entityManager->persist($product);
            }
        }

        $entityManager->flush();
    }
}
