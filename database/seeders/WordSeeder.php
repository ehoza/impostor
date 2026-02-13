<?php

namespace Database\Seeders;

use App\Models\Word;
use Illuminate\Database\Seeder;

class WordSeeder extends Seeder
{
    /**
     * Word pairs with ~30% correlation (related but different enough to be tricky)
     * Each pair: crew_word => impostor_word
     */
    private array $wordPairs = [
        // Romanian themed - Food & Ingredients
        'Măr' => 'Pear', // Both fruits
        'Munte' => 'Deal', // Elevation (Hill)
        'Mămăligă' => 'Făină', // Corn flour (Polenta)
        'Sarmale' => 'Varză', // Cabbage rolls ingredient
        'Branză' => 'Cascaval', // Cheese type
        'Pâine' => 'Porumb', // Bread (Corn)
        'Șuncă' => 'Ursu', // Sister (Brother)
        'Oțet' => 'Bere', // Bear (Honey)
        'Cămașă' => 'Mămăligă', // Shirt (Lin)
        'Iarbure' => 'Mătase', // Yarn types
        'Slănină' => 'Salvat', // Bedding (Storage)
        'Pâine' => 'Viezure', // Belt (Strap)
        'Papucă' => 'Papuc', // Father (Mamă)
        'Gurdie' => 'Nuță', // Sister (Frate)
        'Mocan' => 'Opincă', // Footwear (Boots)
        'Ibră' => 'Mătase', // Pants (Shirt)
        'Furtună' => 'Pălărică', // Hat (Cap)
        'Măg'. 'Mătase', // Coat (Shirt)
        'Manșă' => 'Pantofi', // Socks (Shirt)
        'Ciorbă' => 'Creier', // Sausage variant
        'Mici' => 'Chiftele', // Sausage variant
        'Drob' => 'Copan', // Rug (Carpet)
        'Bec' => 'Măr', // Berry (Fruit)
        'Salată' => 'Sare', // Soup (Broth)
        'Borș' => 'Supă', // Garlic (Onion)
        'Zacuscă' => 'Mujde', // Jam (Preserve)
        'Oțet' => 'Miere', // Bear (Wax)
        'Parință' => 'Colț', // Hat (Beret)
        'Jachet' => 'Beci', // Belt (Strap)
        'Papuci' => 'Muci', // Aunt (Mom)
        'Frate' => 'Gurdie', // Sibling (Sister)
        'Verighe' => 'Barză', // Beer (Wine)
        'Sofrană' => 'Țuică', // Wine (Brandy)
        'Țuică' => 'Pruni', // Plum (Pear)
        'Gutuie' => 'Vișină', // Village (City)
        'Casă' => 'Casă', // House (Castle)
        'Curte' => 'Vale', // Valley (Hill)
        'Pădure' => 'Strugure', // Bridge (Arch)
        'Fereastră' => 'Perete', // Neighbor (Related)
        'Prieten' => 'Vecin', // Friend (Enemy)
        'Coleg' => 'Clasă', // Colleague (School)
        'Profesor' => 'Învățător', // Teacher (Mentor)
        'Student' => 'Elev', // Pupil (Student)
        'Doctor' => 'Medic', // Medic (Nurse)
        'Pacient' => 'Client', // Customer (Patient)
        'Portar' => 'Geantă', // Gate (Bear)
        'Vame' => 'Old', // Seller (Buyer)
        'Fereastră' => 'Cumpărat', // Neighbor (Related)
        'Nasă' => 'Plimbă', // Nose (Smell)
        'Ureche' => 'Auz', // Ear (Hearing)
        'Gură' => 'Călcăi', // Throat (Neck)
        'Mușchi' => 'Talpare', // Mustache (Beard)
        'Sprâceană' => 'Ocean', // Sea (Ocean)
        'Iarbure' => 'Bec', // Tree (Forest)
        'Piatră' => 'Clădir', // Rock (Stone)
        'Pământ' => 'Teren', // Earth (Soil)
        'Cer' => 'Cer', // Sky (Heaven)
        'Lumea' => 'Cuvinte', // World (Words)

        // Balkan themed
        'Rakija' => 'Șljivovica', // Plum brandy
        'Burek' => 'Plăcintă', // Pastry dish
        'Kajmak' => 'Smandžek', // Dairy cream
        'Ćevapi' => 'Mici', // Sausage dish
        'Ajvar' => 'Lutenica', // Vegetable spread
        'Lepinja' => 'Beluša kola', // Corn bread
        'Pljeskavica' => 'Ćevapi', // Grilled meat
        'Sarma' => 'Dolma', // Stuffed dish
        'Musaka' => 'Paprikaš', // Potato dish
        'Baklava' => 'Tulumba', // Sweet pastry
        'Lutenica' => 'Pindjur', // Red pepper spread
        'Pindjur' => 'Ajvar', // Ajvar relish
        'Gibanica' => 'Grah', // Cheese pastry
        'Žatobata' => 'Musaka', // Potato casserole
        'Palačinke' => 'Kuvij', // Little necktie
        'Bajadera' => 'Punjena', // Wandering

        // European themed
        'Croissant' => 'Brioche', // Both French pastries
        'Pizza' => 'Calzone', // Both Italian folded dishes
        'Tapas' => 'Pinchos', // Both Spanish appetizers
        'Sauerkraut' => 'Choucroute', // Fermented cabbage dish
        'Goulash' => 'Paprikáš', // Both paprika dishes
        'Pierogi' => 'Uszka', // Both dumpling dishes
        'Haggis' => 'Plăcintă', // Both wrapped dishes
        'Wiener' => 'Schnitzel', // Both viennese meats
        'Bagel' => 'Pretzel', // Both breads
        'Lasagna' => 'Cannelloni', // Both pasta tubes
        'Macaron' => 'Éclair', // Both French pastries
        'Gyros' => 'Döner', // Both street meat wraps
        'Fondue' => 'Raclette', // Melted cheese dish
        'Choucroute' => 'Sauerkraut', // Cabbage dish
        'Uszka' => 'Pierogi', // Dumplings
        'Raclette' => 'Fondue', // Melted cheese
        'Döner' => 'Gyros', // Street meat
        'Cannelloni' => 'Lasagna', // Pasta tubes
        'Pretzel' => 'Bagel', // Bread
        'Schnitzel' => 'Wiener', // Viennese meat
        'Éclair' => 'Macaron', // French pastry
        'Creme' => 'Caramel', // Sweet topping
        'Brioche' => 'Croissant', // Enriched bread
        'Calzone' => 'Pizza', // Folded pizza
        'Pinchos' => 'Tapas', // Spanish appetizer

        // English themed
        'Tea' => 'Coffee', // Both hot beverages
        'Biscuit' => 'Scone', // Both baked goods
        'Soccer' => 'Rugby', // Both ball sports
        'Cricket' => 'Baseball', // Both bat-ball games
        'Pub' => 'Inn', // Both drinking places
        'Rain' => 'Snow', // Both precipitation
        'Queue' => 'Line', // Same concept
        'Lift' => 'Hoist', // Raising equipment
        'Flat' => 'Studio', // Apartment types
        'Autumn' => 'Fall', // Same season
        'Crisps' => 'Chips', // Both potato snacks
        'Sofa' => 'Settee', // Both seating
        'Boot' => 'Bonnet', // Both car storage
        'Hood' => 'Trunk', // Both cargo area
        'Torch' => 'Flashlight', // Both light sources
        'Jumper' => 'Cardigan', // Both outerwear
        'Lorry' => 'Truck', // Both trucks
        'Nappy' => 'Diaper', // Both baby products
        'Dustbin' => 'Wheelie', // Both waste bins
        'Trainers' => 'Sneakers', // Both shoes
        'Postcode' => 'Address', // Both postal codes
        'Mobile' => 'Cell', // Both phones
        'Chemist' => 'Pharmacy', // Both drug stores
        'Garden' => 'Yard', // Both outdoor areas
        'Hoover' => 'Vacuum', // Both cleaners
        'Eraser' => 'Correction fluid', // Both correction tools
        'Biscuit' => 'Cookie', // Sweet treats
        'Couch' => 'Sofa', // Both seating
        'Truck' => 'Lorry', // Both vehicles
        'Diaper' => 'Nappy', // Both baby products
        'Bonnet' => 'Hood', // Both car parts
        'Porch' => 'Deck', // Both house parts
        'Cellar' => 'Attic', // House storage
        'Basement' => 'Cellar', // Underground
        'Stairs' => 'Steps', // Both climbing
        'Chimney' => 'Flue', // Both ventilation
        'Roof' => 'Shingles', // Both covering
        'Gutter' => 'Downspout', // Water drainage
        'Patio' => 'Deck', // Outdoor platform
        'Driveway' => 'Walkway', // Access path
        'Pavement' => 'Sidewalk', // Walkway surface
        'Garage' => 'Carport', // Vehicle storage
        'Sunglasses' => 'Glasses', // Eyewear
        'Spectacles' => 'Earrings', // Jewelry
        'Bracelet' => 'Watch', // Timepiece
        'Necklace' => 'Ring', // Jewelry
        'Brooch' => 'Tie', // Jewelry
        'Ring' => 'Brooch', // Jewelry
        'Watch' => 'Bracelet', // Timepiece
        'Anklet' => 'Sock', // Footwear
        'Stocking' => 'Tights', // Legwear
        'Scarf' => 'Muffler', // Neckwear
        'Gloves' => 'Mittens', // Handwear
        'Jacket' => 'Coat', // Outerwear
        'Coat' => 'Jacket', // Outerwear
        'Trousers' => 'Pants', // Bottom wear
        'Pants' => 'Shorts', // Bottom wear
        'Shorts' => 'Skirt', // Bottom wear
        'Dress' => 'Gown', // One piece
        'Skirt' => 'Dress', // One piece
        'Suit' => 'Tuxedo', // Formal
        'Vest' => 'Waistcoat', // Layering
        'Belt' => 'Suspenders', // Support
        'Tie' => 'Cravat', // Neckwear
        'Bowtie' => 'Necktie', // Neckwear
        'Wallet' => 'Purse', // Money holder
        'Purse' => 'Pocketbook', // Money holder
        'Briefcase' => 'Backpack', // Carrier
        'Backpack' => 'Suitcase', // Luggage
        'Suitcase' => 'Trunk', // Luggage
        'Luggage' => 'Baggage', // Luggage
        'Trunk' => 'Coffin', // Storage
        'Coffin' => 'Desk', // Furniture
        'Desk' => 'Counter', // Furniture
        'Counter' => 'Bar', // Furniture
        'Table' => 'Shelf', // Furniture
        'Shelf' => 'Cupboard', // Storage
        'Cupboard' => 'Wardrobe', // Storage
        'Wardrobe' => 'Drawer', // Storage
        'Drawer' => 'Chest', // Storage
        'Chest' => 'Box', // Storage
        'Box' => 'Crate', // Storage
        'Crate' => 'Bin', // Storage
        'Bin' => 'Basket', // Container
        'Basket' => 'Hamper', // Container
        'Hamper' => 'Coffer', // Storage
        'Coffer' => 'Safe', // Security
        'Safe' => 'Vault', // Security
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $wordMap = [];

        // Process paired words
        foreach ($this->wordPairs as $crewWord => $impostorWord) {
            $crew = Word::firstOrCreate(
                ['word' => $crewWord],
                [
                    'category' => $this->categorizeWord($crewWord),
                    'definition' => null,
                    'is_impostor_word' => false,
                    'difficulty' => rand(1, 3),
                ]
            );

            $impostor = Word::firstOrCreate(
                ['word' => $impostorWord],
                [
                    'category' => $this->categorizeWord($impostorWord),
                    'definition' => null,
                    'is_impostor_word' => true,
                    'difficulty' => rand(1, 3),
                ]
            );

            $crew->update(['impostor_word_id' => $impostor->id]);
            $wordMap[$crewWord] = $crew;
            $wordMap[$impostorWord] = $impostor;
        }

        $this->command->info('Seeded ' . count($wordMap) . ' word pairs.');
    }

    private function categorizeWord(string $word): string
    {
        $word = trim($word);
        $romanianWords = ['Măr', 'Munte', 'Mămăligă', 'Sarmale', 'Branză', 'Pâine', 'Șuncă', 'Oțet', 'Cămașă', 'Iarbure', 'Slănină', 'Papucă', 'Gurdie', 'Mocan', 'Ibră', 'Furtună', 'Măgăr', 'Manșă', 'Ciorbă', 'Mici', 'Drob', 'Bec', 'Salată', 'Borș', 'Zacuscă', 'Oțet', 'Parință', 'Jachet', 'Papuci', 'Frate', 'Verighe', 'Sofrană', 'Țuică', 'Gutuie', 'Casă', 'Curte', 'Pădure', 'Fereastră', 'Prieten', 'Coleg', 'Profesor', 'Student', 'Doctor', 'Pacient', 'Portar', 'Vame', 'Fereastră', 'Nasă', 'Ureche', 'Gură', 'Mușchi', 'Sprâceană', 'Iarbure', 'Piatră', 'Pământ', 'Cer', 'Lumea'];
        $balkanWords = ['Rakija', 'Burek', 'Kajmak', 'Ćevapi', 'Ajvar', 'Lepinja', 'Pljeskavica', 'Sarma', 'Musaka', 'Baklava', 'Lutenica', 'Pindjur', 'Gibanica', 'Žatobata', 'Palačinke', 'Bajadera', 'Kuvij', 'Vlahov'];
        $europeanWords = ['Croissant', 'Brioche', 'Pizza', 'Calzone', 'Tapas', 'Pinchos', 'Sauerkraut', 'Choucroute', 'Goulash', 'Paprikáš', 'Pierogi', 'Uszka', 'Haggis', 'Plăcintă', 'Wiener', 'Schnitzel', 'Bagel', 'Lasagna', 'Cannelloni', 'Macaron', 'Gyros', 'Döner', 'Fondue', 'Raclette', 'Choucroute', 'Uszka', 'Raclette', 'Döner', 'Cannelloni', 'Pretzel', 'Schnitzel', 'Éclair', 'Creme', 'Calzone', 'Pinchos'];
        $englishWords = ['Tea', 'Biscuit', 'Scone', 'Soccer', 'Rugby', 'Cricket', 'Baseball', 'Pub', 'Inn', 'Rain', 'Snow', 'Queue', 'Line', 'Lift', 'Hoist', 'Flat', 'Studio', 'Autumn', 'Crisps', 'Chips', 'Sofa', 'Settee', 'Boot', 'Bonnet', 'Hood', 'Trunk', 'Torch', 'Flashlight', 'Jumper', 'Cardigan', 'Lorry', 'Truck', 'Nappy', 'Diaper', 'Dustbin', 'Trainers', 'Postcode', 'Address', 'Mobile', 'Cell', 'Chemist', 'Pharmacy', 'Garden', 'Yard', 'Hoover', 'Eraser', 'Biscuit', 'Cookie', 'Couch', 'Truck', 'Diaper', 'Bonnet', 'Porch', 'Deck', 'Driveway', 'Pavement', 'Garage', 'Sunglasses', 'Spectacles', 'Earrings', 'Bracelet', 'Watch', 'Anklet', 'Stocking', 'Scarf', 'Gloves', 'Jacket', 'Coat', 'Trousers', 'Pants', 'Shorts', 'Skirt', 'Dress', 'Suit', 'Vest', 'Belt', 'Suspenders', 'Tie', 'Bowtie', 'Necktie', 'Wallet', 'Purse', 'Briefcase', 'Backpack', 'Suitcase', 'Luggage', 'Trunk', 'Coffin', 'Desk', 'Counter', 'Table', 'Shelf', 'Cupboard', 'Wardrobe', 'Drawer', 'Chest', 'Box', 'Crate', 'Bin', 'Basket', 'Hamper', 'Coffer', 'Safe', 'Vault'];

        if (in_array($word, $romanianWords)) {
            return 'Romanian';
        }

        if (in_array($word, $balkanWords)) {
            return 'Balkan';
        }

        if (in_array($word, $europeanWords)) {
            return 'European';
        }

        if (in_array($word, $englishWords)) {
            return 'English';
        }

        return 'General';
    }
}
