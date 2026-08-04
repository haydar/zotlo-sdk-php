<?php

namespace Zotlo\Connect\Tests\Response;

use PHPUnit\Framework\TestCase;
use Zotlo\Connect\Enum\PaymentMethod;
use Zotlo\Connect\Response\TransactionResponse;

class TransactionResponseTest extends TestCase
{
    public function testPaypalIsResolvedFromTheTopLevelPaymentMethod()
    {
        $transaction = $this->transaction('paypal');

        $this->assertSame(PaymentMethod::PAYPAL, $transaction->getEffectivePaymentMethod());
    }

    public function testPaypalIsResolvedRegardlessOfCase()
    {
        $transaction = $this->transaction('PayPal');

        $this->assertSame(PaymentMethod::PAYPAL, $transaction->getEffectivePaymentMethod());
    }

    public function testPaypalWinsOverCustomParameters()
    {
        $transaction = $this->transaction('paypal', ['threeds' => 1, 'apm' => ['paymentMethod' => 'applePay']]);

        $this->assertSame(PaymentMethod::PAYPAL, $transaction->getEffectivePaymentMethod());
    }

    public function testApplePayIsResolvedFromApm()
    {
        $transaction = $this->transaction('creditCard', ['apm' => ['paymentMethod' => 'applePay']]);

        $this->assertSame(PaymentMethod::APPLE_PAY, $transaction->getEffectivePaymentMethod());
    }

    public function testGooglePayIsResolvedFromApm()
    {
        $transaction = $this->transaction('creditCard', ['apm' => ['paymentMethod' => 'googlePay']]);

        $this->assertSame(PaymentMethod::GOOGLE_PAY, $transaction->getEffectivePaymentMethod());
    }

    public function testCardWithIntegerThreedsFlagIsThreeD()
    {
        $transaction = $this->transaction('creditCard', ['threeds' => 1]);

        $this->assertSame(PaymentMethod::THREE_D, $transaction->getEffectivePaymentMethod());
    }

    public function testCardWithStringThreedsFlagIsThreeD()
    {
        $transaction = $this->transaction('creditCard', ['threeds' => '1']);

        $this->assertSame(PaymentMethod::THREE_D, $transaction->getEffectivePaymentMethod());
    }

    public function testCardWithFalsyThreedsFlagIsNone3d()
    {
        $this->assertSame(
            PaymentMethod::NONE_3D,
            $this->transaction('creditCard', ['threeds' => 0])->getEffectivePaymentMethod()
        );

        $this->assertSame(
            PaymentMethod::NONE_3D,
            $this->transaction('creditCard', ['threeds' => '0'])->getEffectivePaymentMethod()
        );
    }

    public function testCardWithoutThreedsFlagIsNone3d()
    {
        $transaction = $this->transaction('creditCard', ['someOtherFlag' => 1]);

        $this->assertSame(PaymentMethod::NONE_3D, $transaction->getEffectivePaymentMethod());
    }

    public function testNullCustomParametersIsNone3d()
    {
        $transaction = $this->transaction('creditCard', null);

        $this->assertSame(PaymentMethod::NONE_3D, $transaction->getEffectivePaymentMethod());
    }

    public function testMissingCustomParametersIsNone3d()
    {
        $data = $this->payload();
        $data['paymentMethod'] = 'creditCard';
        unset($data['custom_parameters']);

        $transaction = new TransactionResponse($data);

        $this->assertSame(PaymentMethod::NONE_3D, $transaction->getEffectivePaymentMethod());
    }

    public function testMissingPaymentMethodIsNone3d()
    {
        $data = $this->payload();
        unset($data['paymentMethod']);

        $transaction = new TransactionResponse($data);

        $this->assertSame(PaymentMethod::NONE_3D, $transaction->getEffectivePaymentMethod());
    }

    public function testUnknownApmPaymentMethodIsNone3d()
    {
        $transaction = $this->transaction('creditCard', ['apm' => ['paymentMethod' => 'someNewWallet']]);

        $this->assertSame(PaymentMethod::NONE_3D, $transaction->getEffectivePaymentMethod());
    }

    public function testUnknownApmPaymentMethodIsNone3dEvenWithThreedsSet()
    {
        $transaction = $this->transaction('creditCard', ['threeds' => 1, 'apm' => ['paymentMethod' => 'someNewWallet']]);

        $this->assertSame(PaymentMethod::NONE_3D, $transaction->getEffectivePaymentMethod());
    }

    public function testMalformedApmWithoutPaymentMethodKeyIsNone3d()
    {
        $transaction = $this->transaction('creditCard', ['apm' => ['provider' => 'stripe']]);

        $this->assertSame(PaymentMethod::NONE_3D, $transaction->getEffectivePaymentMethod());
    }

    public function testApmThatIsNotAnArrayIsNone3d()
    {
        $transaction = $this->transaction('creditCard', ['apm' => 'applePay']);

        $this->assertSame(PaymentMethod::NONE_3D, $transaction->getEffectivePaymentMethod());
    }

    public function testEmptyApmFallsBackToThreedsResolution()
    {
        $this->assertSame(
            PaymentMethod::THREE_D,
            $this->transaction('creditCard', ['apm' => null, 'threeds' => 1])->getEffectivePaymentMethod()
        );

        $this->assertSame(
            PaymentMethod::NONE_3D,
            $this->transaction('creditCard', ['apm' => [], 'threeds' => 0])->getEffectivePaymentMethod()
        );
    }

    public function testNonScalarThreedsFlagIsNone3d()
    {
        $transaction = $this->transaction('creditCard', ['threeds' => ['unexpected']]);

        $this->assertSame(PaymentMethod::NONE_3D, $transaction->getEffectivePaymentMethod());
    }

    public function testGetPaymentMethodStillReturnsTheRawWalletPayloadValue()
    {
        $transaction = $this->transaction('creditCard', ['apm' => ['paymentMethod' => 'applePay']]);

        $this->assertSame('creditCard', $transaction->getPaymentMethod());
        $this->assertSame(PaymentMethod::APPLE_PAY, $transaction->getEffectivePaymentMethod());
    }

    public function testResolutionFollowsTheSettersAndDoesNotGoStale()
    {
        $transaction = $this->transaction('creditCard', ['threeds' => 1]);
        $this->assertSame(PaymentMethod::THREE_D, $transaction->getEffectivePaymentMethod());

        $transaction->setCustomParameters(['apm' => ['paymentMethod' => 'googlePay']]);
        $this->assertSame(PaymentMethod::GOOGLE_PAY, $transaction->getEffectivePaymentMethod());

        $transaction->setPaymentMethod('paypal');
        $this->assertSame(PaymentMethod::PAYPAL, $transaction->getEffectivePaymentMethod());

        $transaction->setPaymentMethod('creditCard');
        $transaction->setCustomParameters(null);
        $this->assertSame(PaymentMethod::NONE_3D, $transaction->getEffectivePaymentMethod());
    }

    /**
     * @param string|null $paymentMethod
     * @param array|null $customParameters
     * @return TransactionResponse
     */
    private function transaction($paymentMethod, $customParameters = null)
    {
        return new TransactionResponse($this->payload([
            'paymentMethod' => $paymentMethod,
            'custom_parameters' => $customParameters,
        ]));
    }

    /**
     * A webhook payload with every non-nullable field populated.
     *
     * @param array $overrides
     * @return array
     */
    private function payload(array $overrides = [])
    {
        return array_merge([
            'id' => 1,
            'team_id' => 2,
            'app_id' => 3,
            'provider_id' => 4,
            'quantity' => 1,
            'price' => 9.99,
            'package_price' => 9.99,
            'transaction_id' => 'trx-1',
            'paymentMethod' => 'creditCard',
            'custom_parameters' => null,
        ], $overrides);
    }
}
