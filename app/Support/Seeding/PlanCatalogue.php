<?php

declare(strict_types=1);

namespace App\Support\Seeding;

/**
 * Catalogue of synthetic mobile plans and the population profile used by the
 * demo data generator.
 *
 * NO REAL CUSTOMER DATA IS USED ANYWHERE IN THIS PROJECT. Names are assembled
 * from a fixed list of common given names and initials, districts are real
 * Sri Lankan administrative districts (public geography, not personal data) and
 * every numeric attribute is randomly generated.
 */
final class PlanCatalogue
{
    /**
     * Plan templates. Prices are in LKR and are deliberately spread across the
     * low / mid / high bands so the "high monthly cost" risk factor and the
     * churn-by-plan analysis have meaningful variation.
     *
     * @var list<array{
     *     plan_id: string,
     *     plan_name: string,
     *     plan_type: string,
     *     monthly_price: float,
     *     data_gb: float,
     *     voice_minutes: int,
     *     sms_count: int,
     *     status: string,
     *     description: string
     * }>
     */
    public const PLANS = [
        [
            'plan_id' => 'P001',
            'plan_name' => 'Basic Value',
            'plan_type' => 'Prepaid',
            'monthly_price' => 495.0,
            'data_gb' => 2.0,
            'voice_minutes' => 100,
            'sms_count' => 50,
            'status' => 'Active',
            'description' => 'Entry-level prepaid package for occasional voice and messaging users.',
        ],
        [
            'plan_id' => 'P002',
            'plan_name' => 'Everyday Talk',
            'plan_type' => 'Prepaid',
            'monthly_price' => 795.0,
            'data_gb' => 5.0,
            'voice_minutes' => 250,
            'sms_count' => 100,
            'status' => 'Active',
            'description' => 'Balanced voice-led prepaid package for everyday callers.',
        ],
        [
            'plan_id' => 'P003',
            'plan_name' => 'Data Plus',
            'plan_type' => 'Prepaid',
            'monthly_price' => 1299.0,
            'data_gb' => 20.0,
            'voice_minutes' => 500,
            'sms_count' => 100,
            'status' => 'Active',
            'description' => 'High data prepaid package for heavy social media use.',
        ],
        [
            'plan_id' => 'P004',
            'plan_name' => 'Night Owl',
            'plan_type' => 'Prepaid',
            'monthly_price' => 999.0,
            'data_gb' => 12.0,
            'voice_minutes' => 300,
            'sms_count' => 80,
            'status' => 'Active',
            'description' => 'Off-peak data package aimed at night-time streaming.',
        ],
        [
            'plan_id' => 'P005',
            'plan_name' => 'Unlimited Max',
            'plan_type' => 'Prepaid',
            'monthly_price' => 2499.0,
            'data_gb' => 45.0,
            'voice_minutes' => 900,
            'sms_count' => 200,
            'status' => 'Active',
            'description' => 'Premium uncapped-style prepaid package with a large data allowance.',
        ],
        [
            'plan_id' => 'P006',
            'plan_name' => 'Student Connect',
            'plan_type' => 'Prepaid',
            'monthly_price' => 595.0,
            'data_gb' => 8.0,
            'voice_minutes' => 200,
            'sms_count' => 75,
            'status' => 'Active',
            'description' => 'Discounted prepaid package for student segments.',
        ],
        [
            'plan_id' => 'P007',
            'plan_name' => 'Home Postpaid Lite',
            'plan_type' => 'Postpaid',
            'monthly_price' => 1450.0,
            'data_gb' => 15.0,
            'voice_minutes' => 400,
            'sms_count' => 100,
            'status' => 'Active',
            'description' => 'Low-tier postpaid plan with a monthly bill and no top-up needed.',
        ],
        [
            'plan_id' => 'P008',
            'plan_name' => 'Home Postpaid Plus',
            'plan_type' => 'Postpaid',
            'monthly_price' => 2199.0,
            'data_gb' => 30.0,
            'voice_minutes' => 700,
            'sms_count' => 150,
            'status' => 'Active',
            'description' => 'Mid-tier postpaid plan for households sharing a connection.',
        ],
        [
            'plan_id' => 'P009',
            'plan_name' => 'Business Connect',
            'plan_type' => 'Postpaid',
            'monthly_price' => 3450.0,
            'data_gb' => 60.0,
            'voice_minutes' => 1500,
            'sms_count' => 300,
            'status' => 'Active',
            'description' => 'Corporate and SME postpaid plan with pooled data allowance.',
        ],
        [
            'plan_id' => 'P010',
            'plan_name' => 'Enterprise Flex',
            'plan_type' => 'Postpaid',
            'monthly_price' => 5890.0,
            'data_gb' => 120.0,
            'voice_minutes' => 3000,
            'sms_count' => 500,
            'status' => 'Active',
            'description' => 'Enterprise postpaid plan with a large pooled data bundle.',
        ],
        [
            'plan_id' => 'P011',
            'plan_name' => 'Legacy Talk 200',
            'plan_type' => 'Prepaid',
            'monthly_price' => 350.0,
            'data_gb' => 1.0,
            'voice_minutes' => 200,
            'sms_count' => 30,
            'status' => 'Discontinued',
            'description' => 'Retired voice-only package retained for historical subscriptions.',
        ],
        [
            'plan_id' => 'P012',
            'plan_name' => 'Fibre Backup SIM',
            'plan_type' => 'Hybrid',
            'monthly_price' => 950.0,
            'data_gb' => 10.0,
            'voice_minutes' => 350,
            'sms_count' => 90,
            'status' => 'Active',
            'description' => 'Backup voice SIM bundled with a fixed-broadband customer.',
        ],
    ];

    /**
     * Weighted plan assignment by customer segment. Corporate and SME customers
     * are steered towards the higher priced postpaid plans, which reproduces the
     * affordability pressure described in the business problem.
     *
     * @return array<string, float>
     */
    public static function weightsForSegment(string $segment): array
    {
        return match ($segment) {
            'Corporate' => [
                'P009' => 42.0, 'P010' => 30.0, 'P008' => 12.0,
                'P007' => 8.0, 'P005' => 4.0, 'P012' => 4.0,
            ],
            'SME' => [
                'P008' => 30.0, 'P009' => 22.0, 'P007' => 16.0,
                'P005' => 10.0, 'P012' => 10.0, 'P003' => 6.0, 'P010' => 6.0,
            ],
            default => [
                'P001' => 16.0, 'P002' => 18.0, 'P003' => 20.0, 'P004' => 14.0,
                'P005' => 9.0, 'P006' => 11.0, 'P007' => 6.0, 'P008' => 4.0,
                'P012' => 2.0,
            ],
        };
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function byId(): array
    {
        $indexed = [];

        foreach (self::PLANS as $plan) {
            $indexed[$plan['plan_id']] = $plan;
        }

        return $indexed;
    }

    /**
     * Given names used to build obviously synthetic customer names such as
     * "Synth Customer 00001". The literal "Synth" prefix makes it impossible for
     * a demo record to be mistaken for a real person.
     *
     * @var list<string>
     */
    public const GIVEN_NAMES = [
        'Nimal', 'Kamal', 'Suresh', 'Ruwan', 'Dinesh', 'Chamara', 'Ashan', 'Tharindu',
        'Sanduni', 'Ishara', 'Nadeesha', 'Hiruni', 'Shanthi', 'Menaka', 'Kanchana',
        'Sameera', 'Buddika', 'Nuwan', 'Janaka', 'Rohana', 'Suranga', 'Lakmal',
    ];

    /**
     * District weights approximating the urban / rural population spread so that
     * the "churn by district" chart shows a realistic long-tail distribution.
     *
     * @var array<string, float>
     */
    public const DISTRICT_WEIGHTS = [
        'Colombo' => 16.0, 'Gampaha' => 12.0, 'Kalutara' => 7.0, 'Kandy' => 8.5,
        'Matale' => 3.5, 'Nuwara Eliya' => 2.5, 'Galle' => 7.0, 'Matara' => 4.5,
        'Hambantota' => 2.5, 'Jaffna' => 4.0, 'Kilinochchi' => 2.0, 'Mannar' => 1.0,
        'Vavuniya' => 1.5, 'Mullaitivu' => 0.8, 'Batticaloa' => 3.0, 'Ampara' => 1.8,
        'Trincomalee' => 2.2, 'Kurunegala' => 7.0, 'Puttalam' => 2.5,
        'Anuradhapura' => 3.0, 'Polonnaruwa' => 1.5, 'Badulla' => 2.5,
        'Monaragala' => 1.2, 'Ratnapura' => 2.5, 'Kegalle' => 2.0,
    ];

    /**
     * Complaint templates keyed by category. All text is generic and written for
     * this prototype - no verbatim real customer complaint is reproduced.
     *
     * @var array<string, list<string>>
     */
    public const COMPLAINT_TEMPLATES = [
        'Network' => [
            'Frequent call drops in the area during evening hours.',
            'No mobile signal inside the premises for several days.',
            'Poor voice quality and one-way audio on calls.',
        ],
        'Billing' => [
            'Incorrect monthly charge appears on the account statement.',
            'Duplicate recharge deducted from the prepaid balance.',
            'Requested refund for a failed auto-debit has not been processed.',
        ],
        'SIM' => [
            'SIM card not detected after replacement.',
            'SIM card from the new batch fails to register on the network.',
            'SIM card damaged and requires replacement at a retail outlet.',
        ],
        'Data' => [
            'Data bundle exhausted well before the advertised allowance.',
            'Data speeds drop to near zero during peak hours.',
            'Data session disconnects repeatedly on the same handset.',
        ],
        'Customer Service' => [
            'Long waiting time when contacting the call centre.',
            'Agent could not resolve the query and promised a callback that never arrived.',
            'Repeat visits to the service centre for the same unresolved issue.',
        ],
        'Package' => [
            'Package renewal failed although sufficient balance was available.',
            'Requested package change has not taken effect for two billing cycles.',
            'Bundle details in the app do not match the activated package.',
        ],
        'Other' => [
            'General dissatisfaction with the service experience.',
            'Store locator information is inaccurate.',
            'Unclassified feedback submitted through the app.',
        ],
    ];
}
