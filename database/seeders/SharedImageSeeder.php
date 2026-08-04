<?php

namespace Database\Seeders;

use App\Enums\ImageType;
use App\Models\Image;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Seeds the shared icon library: images with no owner, usable by everyone but
 * editable by nobody.
 *
 * The files already ship in public/images, so nothing is copied; the row just
 * records the path relative to that folder and the app links straight to it.
 *
 * The folder a file sits in decides what it can be used for: accounts/,
 * categories/ and books/ each feed their own picker.
 *
 * Fill in the export icon id next to each file to have exports carry the id
 * the mobile app knows that icon by. Files without one still work; they simply
 * export as the app's default icon.
 *
 * Re-running is safe: rows are matched on their file name and updated in place.
 */
class SharedImageSeeder extends Seeder
{
    /**
     * Path under public/images => export icon id.
     *
     * @var array<string, string|int|null>
     */
    private const ICONS = [
        'categories/category_car.png' => '1',
        'categories/category_cigarette.png' => '2',
        'categories/category_clothing.png' => '3',
        'categories/category_entertainment.png' => '4',
        'categories/category_food.png' => '5',
        'categories/category_health.png' => '6',
        'categories/category_home.png' => '7',
        'categories/category_insurance.png' => '8',
        'categories/category_shopping.png' => '9',
        'categories/category_sport.png' => '10',
        'categories/category_tax.png' => '11',
        'categories/category_telephone.png' => '12',
        'categories/category_bills.png' => '13',
        'categories/category_baby.png' => '14',
        'categories/category_electronics.png' => '15',
        'categories/category_beauty.png' => '16',
        'categories/category_social.png' => '17',
        'categories/category_education.png' => '18',
        'categories/category_transportation.png' => '19',
        'categories/category_transfer_to_deleted.png' => '20',
        'categories/category_awards.png' => '101',
        'categories/category_coupons.png' => '102',
        'categories/category_grants.png' => '103',
        'categories/category_lottery.png' => '104',
        'categories/category_refunds.png' => '105',
        'categories/category_rental.png' => '106',
        'categories/category_salary.png' => '107',
        'categories/category_sale.png' => '108',
        'categories/category_transfer_from_deleted.png' => '109',
        'categories/category_beach.png' => '121',
        'categories/category_books.png' => '122',
        'categories/category_cable.png' => '123',
        'categories/category_celebration.png' => '124',
        'categories/category_cooking.png' => '125',
        'categories/category_drinks.png' => '126',
        'categories/category_gas.png' => '127',
        'categories/category_gift.png' => '128',
        'categories/category_gym.png' => '129',
        'categories/category_holiday.png' => '130',
        'categories/category_internet.png' => '131',
        'categories/category_meal.png' => '132',
        'categories/category_music.png' => '133',
        'categories/category_oil.png' => '134',
        'categories/category_party.png' => '135',
        'categories/category_pet.png' => '136',
        'categories/category_rail.png' => '137',
        'categories/category_shoes.png' => '138',
        'categories/category_toiletries.png' => '139',
        'categories/category_travel.png' => '140',
        'categories/category_vacation.png' => '141',
        'categories/category_apple.png' => '142',
        'categories/category_basketball.png' => '143',
        'categories/category_beer.png' => '144',
        'categories/category_bike.png' => '145',
        'categories/category_camera.png' => '146',
        'categories/category_camping.png' => '147',
        'categories/category_color.png' => '148',
        'categories/category_cpu.png' => '149',
        'categories/category_fireplace.png' => '150',
        'categories/category_fish.png' => '151',
        'categories/category_fruit.png' => '152',
        'categories/category_garden.png' => '153',
        'categories/category_guitar.png' => '154',
        'categories/category_icecream.png' => '155',
        'categories/category_iron.png' => '156',
        'categories/category_laundry.png' => '157',
        'categories/category_motorbike.png' => '158',
        'categories/category_paint.png' => '159',
        'categories/category_phone.png' => '160',
        'categories/category_police.png' => '161',
        'categories/category_soda.png' => '162',
        'categories/category_sofa.png' => '163',
        'categories/category_theater.png' => '164',
        'categories/category_violin.png' => '165',
        'categories/category_computer.png' => '166',
        'categories/category_ecommerce.png' => '167',
        'categories/category_electric.png' => '168',
        'categories/category_game.png' => '169',
        'categories/category_infant.png' => '170',
        'categories/category_market.png' => '171',
        'categories/category_medical.png' => '172',
        'categories/category_repair.png' => '173',
        'categories/category_school.png' => '174',
        'categories/category_work.png' => '175',
        'categories/category_1.png' => '201',
        'categories/category_2.png' => '202',
        'categories/category_3.png' => '203',
        'categories/category_4.png' => '204',
        'categories/category_5.png' => '205',
        'categories/category_6.png' => '206',
        'categories/category_7.png' => '207',
        'categories/category_8.png' => '208',
        'categories/category_9.png' => '209',
        'categories/category_10.png' => '210',
        'categories/category_11.png' => '211',
        'categories/category_12.png' => '212',
        'categories/category_13.png' => '213',
        'categories/category_14.png' => '214',
        'categories/category_15.png' => '215',
        'categories/category_16.png' => '216',
        'categories/category_17.png' => '217',
        'categories/category_18.png' => '218',
        'categories/category_19.png' => '219',
        'categories/category_20.png' => '220',
        'categories/category_21.png' => '221',
        'categories/category_22.png' => '222',
        'categories/category_23.png' => '223',
        'categories/category_24.png' => '224',
        'categories/category_25.png' => '225',
        'categories/category_26.png' => '226',
        'categories/category_27.png' => '227',
        'categories/category_28.png' => '228',
        'categories/category_29.png' => '229',
        'categories/category_30.png' => '230',
        'categories/category_31.png' => '231',
        'categories/category_32.png' => '232',
        'accounts/account_cash.png' => '301',
        'accounts/account_card.png' => '302',
        'accounts/account_savings.png' => '303',
        'accounts/account_master.png' => '304',
        'accounts/account_visa.png' => '305',
        'accounts/account_coins.png' => '306',
        'accounts/account_wallet.png' => '307',
        'accounts/account_business.png' => '308',
        'accounts/account_paypal.png' => '309',
        'accounts/account_idea.png' => '310',
        'accounts/account_amex.png' => '311',
        'accounts/account_pot.png' => '312',
        'accounts/account_bill.png' => '321',
        'accounts/account_factory.png' => '322',
        'accounts/account_plan.png' => '323',
        'accounts/account_work.png' => '324',
        'accounts/account_bank.png' => '325',
        'accounts/account_purse.png' => '326',
        'accounts/account_ebanking.png' => '327',
        'accounts/account_locker.png' => '328',
    ];

    public function run(): void
    {
        $created = 0;
        $updated = 0;
        $missing = [];

        foreach (self::ICONS as $path => $exportIconId) {
            if (! File::exists(public_path('images/'.$path))) {
                $missing[] = $path;

                continue;
            }

            $image = Image::withoutGlobalScopes()->firstWhere('image_name', $path);

            if ($image) {
                $image->update(['export_icon_id' => $exportIconId, 'type' => $this->typeFor($path)]);
                $updated++;

                continue;
            }

            Image::withoutGlobalScopes()->create([
                'user_id' => null,
                'type' => $this->typeFor($path),
                'image_name' => $path,
                'export_icon_id' => $exportIconId,
            ]);

            $created++;
        }

        $removed = $this->removeStale();

        $this->command?->info("Shared icons: {$created} created, {$updated} updated, {$removed} removed.");

        if ($missing !== []) {
            $this->command?->warn('Missing files: '.implode(', ', $missing));
        }
    }

    /**
     * The folder the file sits in decides what it can be used for.
     */
    private function typeFor(string $path): ImageType
    {
        return match (true) {
            str_starts_with($path, 'accounts/') => ImageType::Account,
            str_starts_with($path, 'books/') => ImageType::Book,
            default => ImageType::Category,
        };
    }

    /**
     * Drops shared rows that no longer point at a file in the library, such as
     * ones seeded before the icons were served straight from public/images.
     */
    private function removeStale(): int
    {
        return Image::withoutGlobalScopes()
            ->whereNull('user_id')
            ->whereNotIn('image_name', array_keys(self::ICONS))
            ->delete();
    }
}
