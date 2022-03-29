<?php

namespace App\Controller;

use App\Classe\Cart;
use App\Entity\Order;
use App\Entity\OrderDerails;
use App\Form\OrderType;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Checkout\Session;
use Stripe\Stripe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OrderController extends AbstractController
{

    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @Route("/order", name="app_order")
     */
    public function index(Cart $cart, Request $request): Response
    {
        if(!$this->getUser()->getAddresses()->getValues())
        {
            return $this->redirectToRoute('app_account_address_add');
        }

        $form = $this->createForm(OrderType::class, null, [
            'user' => $this->getUser()
        ]);

        return $this->render('order/index.html.twig', [
            'form' => $form->createView(),
            'cart' => $cart->getFull()
        ]);
    }

    /**
     * @Route("/order/recap", name="app_order_recap", methods={"POST"})
     */
    public function add(Cart $cart, Request $request): Response
    {


        $form = $this->createForm(OrderType::class, null, [
            'user' => $this->getUser()
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // $date = new \DateTime();
            $carrier = $form->get('carriers')->getData();
            $delivery = $form->get('addresses')->getData();
            $delivery_content = $delivery->getFirstname() . ' ' . $delivery->getLastname();
            $delivery_content .= '<br>' . $delivery->getPhone();
            if ($delivery->getCompany()) {
                $delivery_content .= '<br>' . $delivery->getCompany();
            }

            $delivery_content .= '<br>' . $delivery->getAddress();
            $delivery_content .= '<br>' . $delivery->getPostal() . ' ' . $delivery->getCity();
            $delivery_content .= '<br>' . $delivery->getCountry();

            // enregister la commande

            $order = new Order();
            $order->setUser($this->getUser());
            $order->setCreatedAt(new \DateTimeImmutable());
            $order->setCarrierName($carrier->getName());
            $order->setCarrierPrice($carrier->getPrice());
            $order->setDelivery($delivery_content);
            $order->setIsPaid(0);

            $this->entityManager->persist($order);


            $products_for_stripe = [];
            $YOUR_DOMAIN = 'http://127.0.0.1:41627/';

        // enregistrer les produits

            foreach ($cart->getFull() as $product) {
                $orderDetails = new OrderDerails();
                $orderDetails->setMyOrder($order);
                $orderDetails->setProduct($product['product']->getName());
                $orderDetails->setQuantity($product['quantity']);
                $orderDetails->setPrice($product['product']->getPrice());
                $orderDetails->setTotal($product['product']->getPrice() * $product['quantity']);

                $this->entityManager->persist($orderDetails);

                $products_for_stripe[] = [
                    'price_data' => [
                        'currency' => 'eur',
                        'unit_amount' => $product['product']->getPrice(),
                        'product_data' => [
                            'name' => $product['product']->getName(),
                            'images' => [$YOUR_DOMAIN."uploads/" .$product['product']->getIllustration()]
                        ],
                    ],
                    'quantity' => $product['quantity'],
                ];

            }


            // $this->entityManager->flush();

            // This is your test secret API key.
            Stripe::setApiKey('sk_test_51KfjH9LCM9LjHczYDdLb5bCEq4wMCLWdGEON1zGKQPNp2984yzfPQcjVhBV4Od00KbEth5X93PJDZPYfNNFvDgeO00AwcQuf8b');

            header('Content-Type: application/json');

            $YOUR_DOMAIN = 'http://localhost:4242/public';

            $checkout_session = Session::create([
                'line_items' => [
                    $products_for_stripe
                ],
                'mode' => 'payment',
                'success_url' => $YOUR_DOMAIN . '/success.html',
                'cancel_url' => $YOUR_DOMAIN . '/cancel.html',
            ]);

            header("HTTP/1.1 303 See Other");
            header("Location: " . $checkout_session->url);




            return $this->render('order/add.html.twig', [
                'cart' => $cart->getFull(),
                'carrier' => $carrier,
                'delivery' => $delivery_content,
                'stripe_checkout_session' => $checkout_session->id
            ]);



        }
            return $this->redirectToRoute('app_cart');

    }
}
