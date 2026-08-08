<?php

namespace Zotlo\Connect\Enum;

/**
 * Effective payment method of a transaction — the payment *instrument*, as opposed to the
 * raw gateway-level `paymentMethod` string that arrives in the payload.
 *
 * @see \Zotlo\Connect\Response\TransactionResponse::getEffectivePaymentMethod()
 * @package Zotlo\Connect\Enum
 */
class PaymentMethod
{
    const PAYPAL = 'paypal';

    const APPLE_PAY = 'applePay';

    const GOOGLE_PAY = 'googlePay';

    const THREE_D = '3d';

    const NONE_3D = 'none3d';
}
