<?php

declare(strict_types=1);

namespace Modules\Core\Support;

final class SuccessCode
{
    public const HEALTH = 'SYS-HLT-200';

    public const REGISTERED = 'AUTH-REG-200';

    public const LOGGED_IN = 'AUTH-LGN-200';

    public const PROFILE = 'AUTH-ME-200';

    public const REFRESHED = 'AUTH-TKN-200';

    public const LOGGED_OUT = 'AUTH-OUT-200';

    public const ORDER_LIST = 'ORD-LST-200';

    public const ORDER_SHOW = 'ORD-SHW-200';

    public const ORDER_CREATED = 'ORD-CRT-200';

    public const ORDER_UPDATED = 'ORD-UPD-200';

    public const ORDER_DELETED = 'ORD-DEL-200';

    public const PAYMENT_LIST = 'PAY-LST-200';

    public const PAYMENT_SHOW = 'PAY-SHW-200';

    public const PAYMENT_PROCESSED = 'PAY-PRC-200';

    public const PAYMENT_METHODS = 'PAY-MTH-200';
}
