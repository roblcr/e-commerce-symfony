<?php

namespace App\Controller;

use App\Classe\Cart;
use Stripe\Checkout\Session;
use Stripe\Stripe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StripeController extends AbstractController
{
    /**
     * @Route("/order/create-session", name="stripe_create_session")
     */
    public function index(Cart $cart): Response
    {
        $products_for_stripe = [];
        $YOUR_DOMAIN = 'http://localhost:4242/public';

        foreach ($cart->getFull() as $product) {

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


        Stripe::setApiKey('sk_test_51KfjH9LCM9LjHczYDdLb5bCEq4wMCLWdGEON1zGKQPNp2984yzfPQcjVhBV4Od00KbEth5X93PJDZPYfNNFvDgeO00AwcQuf8b');



        $checkout_session = Session::create([
            'line_items' => [
                $products_for_stripe
            ],
            'mode' => 'payment',
            'success_url' => $YOUR_DOMAIN . '/success.html',
            'cancel_url' => $YOUR_DOMAIN . '/cancel.html',
        ]);

        $response = new JsonResponse(['id' => $checkout_session->id]);
        return $response;
    }
}
