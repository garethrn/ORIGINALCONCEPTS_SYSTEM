<?php

declare(strict_types=1);

namespace App\Enums;

enum TicketCategory: string
{
    case ItSupport = 'it_support';
    case WebDev = 'web_dev';
    case WhatsappDev = 'whatsapp_dev';
    case SoftwareDev = 'software_dev';
    case Networking = 'networking';
    case CloudVps = 'cloud_vps';
    case Cctv = 'cctv';
    case Telephones = 'telephones';
    case Printing = 'printing';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ItSupport => 'IT support',
            self::WebDev => 'Web development',
            self::WhatsappDev => 'WhatsApp development',
            self::SoftwareDev => 'Software development',
            self::Networking => 'Networking',
            self::CloudVps => 'Cloud / VPS',
            self::Cctv => 'CCTV',
            self::Telephones => 'Telephones',
            self::Printing => 'Printing',
            self::Other => 'Other',
        };
    }
}
