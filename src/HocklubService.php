<?php

namespace Mvune\Hocklubs;

use Medoo\Medoo;
use voku\helper\HtmlDomParser;

class HocklubService
{
    const URL = 'https://hockey.nl/clubs/';

    private $clubs = [];

    /**
     * Get all `Hocklub`s listed on `self::URL`.
     * 
     * @return Hocklub[]
     */
    public function getAll()
    {
        if (!empty($this->clubs)) {
            return $this->clubs;
        }

        ini_set('max_execution_time', 300);

        $overview = HtmlDomParser::file_get_html(self::URL);

        foreach ($overview->find('.ticket') as $ticket) {
            $club = [];
            $club["link"] = $ticket->find('a.ticket-action')[0]->href ?? "";
            $club["name"] = $ticket->find('.ticket-body > .ticket-label')[0]->plaintext ?? "";
            $club["logo"] = $ticket->find('.ticket-aside > img')[0]->src ?? "";

            if ($club["name"] == 'KNHB') {
                continue;
            }

            $clubPage = HtmlDomParser::file_get_html($club["link"]);

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
                    $club['street'] = trim($matches[1] ?? '');
                    $club['postal_code'] = $matches[2] ?? '';
                    $club['city'] = trim($matches[3] ?? '');
                    continue;
                }

                $club[$property] = $value;
            }

            $this->clubs[] = $this->mapToHocklub($club);
        }

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
     * Create a `Hocklub` from an array containing the club data.
     * 
     * @param array $club
     * @return Hocklub
     */
    private function mapToHocklub(array $club)
    {
        $hocklub = new Hocklub;

        $hocklub->name = $club['name'] ?? '';
        $hocklub->logo = $club['logo'] ?? '';
        $hocklub->link = $club['link'] ?? '';
        $hocklub->phone = $club['Telefoonnummer'] ?? '';
        $hocklub->email = $club['E-mailadres'] ?? '';
        $hocklub->website = $club['Website'] ?? '';
        $hocklub->street = $club['street'] ?? '';
        $hocklub->postal_code = $club['postal_code'] ?? '';
        $hocklub->city = $club['city'] ?? '';
        $hocklub->outfit = $club['Omschrijving tenue'] ?? '';
        $hocklub->pitches = $club['Soort velden'] ?? '';
        $hocklub->members = $club['Aantal leden'] ?? '';
        $hocklub->founded = $club['Opgericht in'] ?? '';

        return $hocklub;
    }
}
