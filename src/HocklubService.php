<?php

namespace Mvune\Hocklubs;

use Medoo\Medoo;
use Gumlet\ImageResize;
use voku\helper\HtmlDomParser;

class HocklubService
{
    const URL = 'https://hockey.nl/clubs/';

    private $clubs = [];
    private $fetched = false;
    private $prefetched = false;

    public function __construct()
    {
        ini_set('max_execution_time', 300);
    }

    /**
     * Get all `Hocklub`s listed on `self::URL`.
     * 
     * @return Hocklub[]
     */
    public function getAll()
    {
        $this->fetch();
        return $this->clubs;
    }

    /**
     * Store all `Hocklub`s in a given SQLite database.
     * 
     * @param string $dbFile  Filepath to an SQLite database file. If the file does not exist, it will be created.
     * @throws HockclubException  If SQLite database file is not writeable or not a database.
     * @return void
     */
    public function exportToSqliteDb(string $dbFile)
    {
        if (!is_file($dbFile) && !touch($dbFile)) {
            throw new HocklubsException('Cannot create SQLite database file.');
        }

        if (!is_writeable($dbFile)) {
            throw new HocklubsException('SQLite database file is not writeable.');
        }
        
        $db = new Medoo([
            'database_type' => 'sqlite',
            'database_file' => $dbFile
        ]);

        if (!$db->query('SELECT * FROM `sqlite_master` LIMIT 1')) {
            throw new HocklubsException('SQLite database file is not actually a database.');
        }

        $this->getAll();

        $db->query("CREATE TABLE IF NOT EXISTS `hocklubs` (
            `id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            `name` TEXT NOT NULL,
            `logo` TEXT,
            `link` TEXT,
            `phone` TEXT,
            `email` TEXT,
            `website` TEXT,
            `street` TEXT,
            `postal_code` TEXT,
            `city` TEXT,
            `outfit` TEXT,
            `pitches` TEXT,
            `members` INTEGER,
            `founded` TEXT
        )");

        foreach ($this->clubs as $club) {
            $db->insert('hocklubs', $club->toArray());
        }
    }

    /**
     * Download all logos and save to folder `$path`.
     * 
     * @param  string $path  Directory to save the logos to. Defaults to cwd.
     * @param  int $scale  Scale to `$scale` percent.
     * @return void
     */
    public function downloadLogos(string $path = null, int $scale = null)
    {
        if ($path && !is_dir($path)) {
            mkdir($path, 0777, true);
        }

        $path = $path ? rtrim($path, '/') . '/' : '';

        $this->prefetch();

        foreach ($this->clubs as $club) {
            $logo = $club->logo ? file_get_contents('https://hockey.nl' . $club->logo) : null;

            if (!$logo) {
                continue;
            }
            
            $image = ImageResize::createFromString($logo);
            $image = $scale ? $image->scale($scale) : $image;
            $image->save($path . basename($club->logo));
        }
    }

    /**
     * Fetch clubs' details.
     * 
     * @return void
     */
    private function fetch()
    {
        if ($this->fetched) {
            return;
        }

        $this->prefetch();

        foreach ($this->clubs as $club) {

            $data = [];
            $clubPage = HtmlDomParser::file_get_html($club->link);

            foreach ($clubPage->find('dt') as $tag) {
                $property = preg_replace('/(Aantal leden)([\s\S]+)/', '$1', $tag->plaintext);
                $value = preg_replace('/(\s)+/', ' ', $tag->next_sibling()->next_sibling()->innertext);
                $value = strip_tags($value);

                if ($property == 'Website') {
                    $value = str_replace(['http://', 'https://'], '', $value);
                }

                if ($property == 'Bezoekadres') {
                    $matches = null;
                    preg_match('/(.+)(\d{4} ?\w{2})(.+)/', $value, $matches);
                    $data['street'] = trim($matches[1] ?? '');
                    $data['postal_code'] = $matches[2] ?? '';
                    $data['city'] = trim($matches[3] ?? '');
                    continue;
                }

                $data[$property] = $value;
            }

            $this->fillWithData($club, $data);
        }

        $this->fetched = true;
    }

    /**
     * Fetch clubs' names, logos and links to detail pages.
     * 
     * @return void
     */
    private function prefetch()
    {
        if ($this->prefetched) {
            return;
        }

        $overview = HtmlDomParser::file_get_html(self::URL);

        foreach ($overview->find('.ticket') as $ticket) {
            $club = new Hocklub;
            $club->link = $ticket->find('a.ticket-action')[0]->href ?? "";
            $club->name = $ticket->find('.ticket-body > .ticket-label')[0]->plaintext ?? "";
            $club->logo = $ticket->find('.ticket-aside > img')[0]->src ?? "";

            if ($club->name == 'KNHB') {
                continue;
            }

            $this->clubs[] = $club;
        }

        $this->prefetched = true;
    }

    /**
     * Fill a `Hocklub` from an array containing the club data.
     *
     * @param  Hocklub $$hocklub
     * @param  array $data
     * @return Hocklub
     */
    private function fillWithData(Hocklub $hocklub, array $data)
    {
        $hocklub->name = $data['name'] ?? $hocklub->name;
        $hocklub->logo = $data['logo'] ?? $hocklub->logo;
        $hocklub->link = $data['link'] ?? $hocklub->link;
        $hocklub->phone = $data['Telefoonnummer'] ?? $hocklub->phone;
        $hocklub->email = $data['E-mailadres'] ?? $hocklub->email;
        $hocklub->website = $data['Website'] ?? $hocklub->website;
        $hocklub->street = $data['street'] ?? $hocklub->street;
        $hocklub->postal_code = $data['postal_code'] ?? $hocklub->postal_code;
        $hocklub->city = $data['city'] ?? $hocklub->city;
        $hocklub->outfit = $data['Omschrijving tenue'] ?? $hocklub->outfit;
        $hocklub->pitches = $data['Soort velden'] ?? $hocklub->pitches;
        $hocklub->members = $data['Aantal leden'] ?? $hocklub->members;
        $hocklub->founded = $data['Opgericht in'] ?? $hocklub->founded;

        return $hocklub;
    }
}
