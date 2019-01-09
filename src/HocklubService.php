<?php

namespace Mvune\Hocklubs;

use SQLite3;
use Medoo\Medoo;
use voku\helper\HtmlDomParser;

class HocklubService
{
    const URL = 'https://hockey.nl/clubs/';

    private $clubs = [];

    /**
     * Get all `Hocklub`s scraped from `self::URL`.
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
                $value = str_replace(["\t", "\n"], '', $tag->next_sibling()->next_sibling()->innertext);
                $club[$property] = $value;
            }

            $this->clubs[] = $this->mapToHocklub($club);
        }

        return $this->clubs;
    }

    /**
     * Store all `Hocklub`s in a given SQLite database.
     * 
     * @param string $dbFile  Filepath to an SQLite database file.
     * @return void
     */
    public function allToSqliteDb(string $dbFile)
    {
        if (!is_file($dbFile) && !extension_loaded('sqlite3')) {
            throw new Exception('Given SQLite database file does not exist or is not a file.');
        }

        if (is_file($dbFile) && !is_writeable($dbFile)) {
            throw new Exception('Given SQLite database file is not writeable.');
        }
        
        $this->getAll();

        if (!is_file($dbFile)) {
            $sqlite3Db = new SQLite3($dbFile);
            $sqlite3Db = null;
        }

        $db = new Medoo([
            'database_type' => 'sqlite',
            'database_file' => $dbFile
        ]);

        $db->query("CREATE TABLE IF NOT EXISTS `hocklubs` (
            `id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            `name` TEXT NOT NULL,
            `logo` TEXT,
            `link` TEXT,
            `phone` TEXT,
            `email` TEXT,
            `website` TEXT,
            `address` TEXT,
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
        $hocklub->address = $club['Bezoekadres'] ?? '';
        $hocklub->outfit = $club['Omschrijving tenue'] ?? '';
        $hocklub->pitches = $club['Soort velden'] ?? '';
        $hocklub->members = $club['Aantal leden'] ?? '';
        $hocklub->founded = $club['Opgericht in'] ?? '';

        return $hocklub;
    }
}