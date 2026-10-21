<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

enum ErrorCode: string
{
    case InternalError = 'SYS-GEN-001';
    case NotFound = 'SYS-GEN-002';
    case MethodNotAllowed = 'SYS-GEN-003';
    case TooManyRequests = 'SYS-GEN-004';
    case Forbidden = 'SYS-GEN-005';
    case HttpError = 'SYS-GEN-006';
    case ValidationFailed = 'SYS-VAL-001';

    case RegistrationValidationFailed = 'AUTH-REG-001';
    case LoginValidationFailed = 'AUTH-LGN-001';
    case InvalidCredentials = 'AUTH-LGN-002';
    case Unauthenticated = 'AUTH-TKN-001';
    case TokenExpired = 'AUTH-TKN-002';

    case OrderListValidationFailed = 'ORD-LST-001';
    case OrderNotFound = 'ORD-SHW-001';
    case OrderCreateValidationFailed = 'ORD-CRT-001';
    case OrderUpdateValidationFailed = 'ORD-UPD-001';
    case OrderItemsLocked = 'ORD-UPD-002';
    case OrderInvalidTransition = 'ORD-UPD-003';
    case OrderDeleteHasPayments = 'ORD-DEL-001';

    case PaymentListValidationFailed = 'PAY-LST-001';
    case PaymentNotFound = 'PAY-SHW-001';
    case PaymentValidationFailed = 'PAY-PRC-001';
    case PaymentOrderNotConfirmed = 'PAY-PRC-002';
    case PaymentGatewayUnsupported = 'PAY-PRC-003';
    case PaymentGatewayFailed = 'PAY-PRC-004';
    case PaymentAlreadyPaid = 'PAY-PRC-005';
    case PaymentIdempotencyConflict = 'PAY-PRC-006';
    case PaymentGatewayMisconfigured = 'PAY-CFG-001';

    public function httpStatus(): int
    {
        return match ($this) {
            self::InternalError => 500,
            self::HttpError => 400,
            self::NotFound, self::OrderNotFound, self::PaymentNotFound => 404,
            self::MethodNotAllowed => 405,
            self::TooManyRequests => 429,
            self::Forbidden => 403,
            self::InvalidCredentials, self::Unauthenticated, self::TokenExpired => 401,
            self::OrderItemsLocked, self::OrderInvalidTransition, self::OrderDeleteHasPayments,
            self::PaymentOrderNotConfirmed, self::PaymentAlreadyPaid, self::PaymentIdempotencyConflict => 409,
            self::PaymentGatewayFailed => 402,
            default => 422,
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::InternalError => 'An unexpected error occurred. Please reference the trace id when contacting support.',
            self::NotFound => 'The requested resource was not found.',
            self::MethodNotAllowed => 'This HTTP method is not allowed for the requested endpoint.',
            self::TooManyRequests => 'Too many requests. Please try again later.',
            self::Forbidden => 'You are not allowed to access this resource.',
            self::HttpError => 'The request could not be completed.',
            self::ValidationFailed => 'The submitted data is invalid.',
            self::RegistrationValidationFailed => 'Registration data is invalid.',
            self::LoginValidationFailed => 'Login data is invalid.',
            self::InvalidCredentials => 'The email or password is incorrect.',
            self::Unauthenticated => 'Authentication is required.',
            self::TokenExpired => 'The authentication token has expired.',
            self::OrderListValidationFailed => 'The order list filters are invalid.',
            self::OrderNotFound => 'The order was not found.',
            self::OrderCreateValidationFailed => 'The order could not be created because the submitted data is invalid.',
            self::OrderUpdateValidationFailed => 'The order could not be updated because the submitted data is invalid.',
            self::OrderItemsLocked => 'Order items can only be edited while the order is pending.',
            self::OrderInvalidTransition => 'The order cannot move to the requested status.',
            self::OrderDeleteHasPayments => 'An order with associated payments cannot be deleted.',
            self::PaymentListValidationFailed => 'The payment list filters are invalid.',
            self::PaymentNotFound => 'The payment was not found.',
            self::PaymentValidationFailed => 'The payment could not be processed because the submitted data is invalid.',
            self::PaymentOrderNotConfirmed => 'Payments can only be processed for confirmed orders.',
            self::PaymentGatewayUnsupported => 'The selected payment gateway is unsupported or disabled.',
            self::PaymentGatewayFailed => 'The payment gateway declined or failed to process the payment.',
            self::PaymentAlreadyPaid => 'This order already has a successful payment.',
            self::PaymentIdempotencyConflict => 'This idempotency key was already used with a different payment request.',
            self::PaymentGatewayMisconfigured => 'The payment gateway is missing required configuration.',
        };
    }
}
