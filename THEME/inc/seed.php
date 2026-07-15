<?php
/**
 * Initial content seed — runs on theme activation if the relevant options don't exist yet.
 * Lifted from the React source's hard-coded copy so a fresh install matches the demo.
 */
if (!defined('ABSPATH')) {
    exit;
}

function fc_seed_initial_content() {
    fc_seed_if_empty('fc_section_hero', [
        'top_label_el'   => '19η Πανελλήνια Συνάντηση Κοινοτήτων ΕΛ/ΛΑΚ',
        'top_label_en'   => '19th Panhellenic FOSS Communities Meeting',
        'brand'          => 'FOSSCOMM',
        'year'           => '2026',
        'email'          => 'hello@fosscomm.gr',
        'socials'        => [
            ['label' => 'YT', 'url' => 'https://youtube.com/@fosscomm'],
            ['label' => 'FB', 'url' => 'https://facebook.com/fosscomm'],
            ['label' => 'TT', 'url' => 'https://tiktok.com/@fosscomm'],
            ['label' => 'IG', 'url' => 'https://instagram.com/fosscomm'],
        ],
        'dates_el'       => '17 — 18 Οκτωβρίου 2026',
        'dates_en'       => '17 — 18 October 2026',
        'venue_el'       => 'Εθνικό Μετσόβιο Πολυτεχνείο · Αθήνα',
        'venue_en'       => 'National Technical University · Athens, GR',
        'cost_el'        => 'Δωρεάν, όπως στην ελευθερία',
        'cost_en'        => 'Free, as in freedom',
        'cta_primary_el' => 'Άνοιξε το πρόγραμμα →',
        'cta_primary_en' => 'Open the schedule →',
        'cta_primary_url' => '#schedule',
        'cta_secondary_el' => 'Γίνε εθελοντής/τρια →',
        'cta_secondary_en' => 'Volunteer →',
        'cta_secondary_url' => '#volunteer',
        'cta_tertiary_el' => 'Γίνε χορηγός →',
        'cta_tertiary_en' => 'Sponsor →',
        'cta_tertiary_url' => '#sponsors',
    ]);

    fc_seed_if_empty('fc_section_manifesto', [
        'title_el' => 'Μια συνάντηση κοινοτήτων, όχι ένα συνέδριο brands.',
        'title_en' => 'A meeting of communities, not a conference of brands.',
        'body_el'  => "Το FOSSCOMM είναι μια ετήσια συνάντηση των κοινοτήτων ελεύθερου λογισμικού της Ελλάδας — εθελοντική, δωρεάν, ανοιχτή σε όλους.\n\nΔεν πουλάμε κάτι. Δεν προωθούμε κάτι. Συναντιόμαστε για να μιλήσουμε, να μάθουμε, να χτίσουμε, και να φύγουμε με λίγη παραπάνω εμπιστοσύνη ο ένας στον άλλον.",
        'body_en'  => "FOSSCOMM is the annual gathering of Greece's free and open source software communities — volunteer-run, free of charge, open to everyone.\n\nWe are not selling anything. We are not pitching anything. We meet to talk, to learn, to build, and to leave with slightly more trust in one another than we arrived with.",
        'stats'    => [
            ['number' => '19',   'label_el' => 'διοργανώσεις από το 2008', 'label_en' => 'editions since 2008'],
            ['number' => '~800', 'label_el' => 'αναμενόμενοι παρευρισκόμενοι', 'label_en' => 'expected attendees'],
            ['number' => '120+', 'label_el' => 'ομιλίες · workshops · lightning', 'label_en' => 'talks · workshops · lightning'],
        ],
    ]);

    // Section headings now live in their own options (parallel to the rows
     // option) so they can be edited from each collection admin page. The
     // values here mirror the strings the templates used to hard-code, so a
     // fresh install reads identically to before.
    fc_seed_if_empty('fc_section_schedule', [
        'title_el' => 'Πρόγραμμα — δύο μέρες.',
        'title_en' => 'Two days. Four rooms. One weekend.',
    ]);
    fc_seed_if_empty('fc_section_news', [
        'title_el' => 'Νέα και ανακοινώσεις.',
        'title_en' => 'News & announcements.',
    ]);
    fc_seed_if_empty('fc_section_speakers', [
        'title_el' => 'Άνθρωποι που εμφανίστηκαν',
        'title_en' => 'People who showed up.',
    ]);
    fc_seed_if_empty('fc_section_sponsors', [
        'title_el' => 'Όσοι κάνουν δυνατό το «δωρεάν».',
        'title_en' => 'The people who make ‘free’ possible.',
    ]);
    fc_seed_if_empty('fc_section_faq', [
        'title_el' => 'Λογικές ερωτήσεις, απλές απαντήσεις.',
        'title_en' => 'Reasonable questions, plain answers.',
    ]);

    // Schedule days — previously hard-coded sat/sun in the template; now a
    // user-editable repeater. Seed with the same two days so existing
    // installs migrating up keep their schedule rendering exactly as it did.
    // Inlined (not calling fc_schedule_default_days()) so the seed has no
    // dependency on admin-only files.
    fc_seed_if_empty('fc_schedule_days', [
        ['key' => 'sat', 'name_el' => 'Σάββατο', 'name_en' => 'Saturday', 'date' => '2026-10-17'],
        ['key' => 'sun', 'name_el' => 'Κυριακή', 'name_en' => 'Sunday',   'date' => '2026-10-18'],
    ]);

    fc_seed_if_empty('fc_tracks', [
        ['slug' => 'open-hardware',     'name_el' => 'Open Hardware',         'name_en' => 'Open Hardware'],
        ['slug' => 'machine-learning',  'name_el' => 'Μηχανική Μάθηση / AI',  'name_en' => 'Machine Learning / AI'],
        ['slug' => 'cloud-edge',        'name_el' => 'Cloud & Edge',          'name_en' => 'Cloud & Edge'],
        ['slug' => 'embedded',          'name_el' => 'Embedded / IoT',        'name_en' => 'Embedded / IoT'],
        ['slug' => 'security',          'name_el' => 'Ασφάλεια',              'name_en' => 'Security'],
        ['slug' => 'legal',             'name_el' => 'Άδειες & Νομικά',       'name_en' => 'Legal & Licensing'],
        ['slug' => 'web',               'name_el' => 'Web',                   'name_en' => 'Web'],
        ['slug' => 'mobile',            'name_el' => 'Mobile',                'name_en' => 'Mobile'],
        ['slug' => 'e-gov',             'name_el' => 'Ηλ. Διακυβέρνηση',      'name_en' => 'e-Government'],
        ['slug' => 'e-health',          'name_el' => 'Ηλ. Υγεία',             'name_en' => 'e-Health'],
        ['slug' => 'blockchain',        'name_el' => 'Blockchain',            'name_en' => 'Blockchain'],
        ['slug' => 'linux-distros',     'name_el' => 'Linux & Διανομές',      'name_en' => 'Linux & Distros'],
        ['slug' => 'devops',            'name_el' => 'DevOps',                'name_en' => 'DevOps'],
        ['slug' => 'documentation',     'name_el' => 'Τεκμηρίωση',            'name_en' => 'Documentation'],
        ['slug' => 'accessibility',     'name_el' => 'Προσβασιμότητα',        'name_en' => 'Accessibility'],
        ['slug' => 'design-ux',         'name_el' => 'Design / UX στο FOSS',  'name_en' => 'Design / UX in FOSS'],
        ['slug' => 'education',         'name_el' => 'Εκπαίδευση',            'name_en' => 'Education'],
        ['slug' => 'networking',        'name_el' => 'Δίκτυα',                'name_en' => 'Networking'],
        ['slug' => 'privacy',           'name_el' => 'Ιδιωτικότητα',          'name_en' => 'Privacy'],
        ['slug' => 'community',         'name_el' => 'Κοινότητα & Διακυβέρνηση', 'name_en' => 'Community & Governance'],
    ]);

    fc_seed_if_empty('fc_speakers', [
        ['name' => 'Maria Papadopoulou', 'photo' => '',
         'role_el' => 'Συντηρήτρια',           'role_en' => 'Maintainer',
         'affiliation_el' => 'GFOSS',           'affiliation_en' => 'GFOSS',
         'bio_el' => 'Σε κάθε FOSSCOMM από το 2009. Τεκμηριώνει αυτό που χτίζουν οι άλλοι.',
         'bio_en' => 'Has been on every FOSSCOMM since 2009. Documents what others build.', 'url' => ''],
        ['name' => 'Kostas Antoniou', 'photo' => '',
         'role_el' => 'Μηχανικός Ασφάλειας',   'role_en' => 'Security Engineer',
         'affiliation_el' => 'Independent',     'affiliation_en' => 'Independent',
         'bio_el' => 'Reproducible builds, supply chain, και πού και πού ένα zine.',
         'bio_en' => 'Reproducible builds, supply chain, and the occasional zine.', 'url' => ''],
        ['name' => 'Eleni Vasileiou', 'photo' => '',
         'role_el' => 'Ερευνήτρια',             'role_en' => 'Researcher',
         'affiliation_el' => 'DemRG',           'affiliation_en' => 'DemRG',
         'bio_el' => 'Local-first, CRDTs, και λογισμικό που σέβεται τα νοικοκυριά.',
         'bio_en' => 'Local-first, CRDTs, and software that respects households.', 'url' => ''],
        ['name' => 'Dimitris Stavrou', 'photo' => '',
         'role_el' => 'SysAdmin',               'role_en' => 'SysAdmin',
         'affiliation_el' => 'Πανεπιστήμιο Κρήτης', 'affiliation_en' => 'University of Crete',
         'bio_el' => 'Τρέχει περισσότερες Mastodon instances απ\'όσες είναι λογικό.',
         'bio_en' => 'Runs more Mastodon instances than is reasonable.', 'url' => ''],
        ['name' => 'Nikos Karras', 'photo' => '',
         'role_el' => 'Μηχανικός ML',           'role_en' => 'ML Engineer',
         'affiliation_el' => 'Ε.Κ. «Αθηνά»',    'affiliation_en' => 'Athena RC',
         'bio_el' => 'Differential privacy, federated learning, slow conferences.',
         'bio_en' => 'Differential privacy, federated learning, slow conferences.', 'url' => ''],
        ['name' => 'Alexandra Lefteris', 'photo' => '',
         'role_el' => 'Συγγραφέας & Developer', 'role_en' => 'Author & Developer',
         'affiliation_el' => '',                'affiliation_en' => '',
         'bio_el' => 'Γράφει ένα βιβλίο για την πολιτική των dependency graphs.',
         'bio_en' => 'Writing a book about the politics of dependency graphs.', 'url' => ''],
        ['name' => 'Yiannis Mavridis', 'photo' => '',
         'role_el' => 'Hardware Hacker',        'role_en' => 'Hardware Hacker',
         'affiliation_el' => 'hsgr',            'affiliation_en' => 'hsgr',
         'bio_el' => 'Κολλάει στα διαλείμματα. Έχει γνώμη για το RISC-V.',
         'bio_en' => 'Solders during Q&A. Has opinions about RISC-V.', 'url' => ''],
        ['name' => 'Sofia Anagnostou', 'photo' => '',
         'role_el' => 'Νομική Σύμβουλος',       'role_en' => 'Legal Counsel',
         'affiliation_el' => 'OW2',             'affiliation_en' => 'OW2',
         'bio_el' => 'Μεταφράζει licenses στα Ελληνικά και πίσω σε λογική.',
         'bio_en' => 'Translates licenses into Greek and back into reason.', 'url' => ''],
    ]);

    fc_seed_if_empty('fc_sessions', [
        ['day' => 'sat', 'time' => '10:00', 'title_el' => 'Έναρξη · Καλώς ήρθατε στο FOSSCOMM 2026', 'title_en' => 'Opening · Welcome to FOSSCOMM 2026', 'speaker' => 'Organizing Committee', 'room' => 'Room 1', 'tracks' => ['community'], 'lang' => 'GR'],
        ['day' => 'sat', 'time' => '10:30', 'title_el' => 'Είκοσι χρόνια ελληνικού FOSS, σε είκοσι λεπτά', 'title_en' => 'Twenty Years of Greek FOSS, in Twenty Minutes', 'speaker' => 'M. Papadopoulou', 'room' => 'Room 1', 'tracks' => ['community'], 'lang' => 'GR'],
        ['day' => 'sat', 'time' => '11:00', 'title_el' => 'Επαναπαραγωγή builds, επαναπαραγωγή εμπιστοσύνης', 'title_en' => 'Reproducible Builds, Reproducible Trust', 'speaker' => 'K. Antoniou', 'room' => 'Room 2', 'tracks' => ['security', 'devops'], 'lang' => 'EN', 'prereq_el' => 'Laptop με Docker + git ≥ 2.40.', 'prereq_en' => 'Laptop with Docker + git ≥ 2.40. Pre-pull ghcr.io/repro/builder:2026.'],
        ['day' => 'sat', 'time' => '12:00', 'title_el' => 'Local-First Λογισμικό & το Συνεταιριστικό Cloud', 'title_en' => 'Local-First Software & the Cooperative Cloud', 'speaker' => 'E. Vasileiou', 'room' => 'Room 2', 'tracks' => ['cloud-edge'], 'lang' => 'EN'],
        ['day' => 'sat', 'time' => '13:30', 'title_el' => 'Lightning Talks · Block I', 'title_en' => 'Lightning Talks · Block I', 'speaker' => '8 speakers · 5 min each', 'room' => 'Room 1', 'tracks' => ['community'], 'lang' => 'GR/EN'],
        ['day' => 'sat', 'time' => '17:30', 'title_el' => 'Keynote · Η σιωπηλή πολιτική των package managers', 'title_en' => 'Keynote · The Quiet Politics of Package Managers', 'speaker' => 'A. Lefteris', 'room' => 'Room 1', 'tracks' => ['community', 'legal'], 'lang' => 'EN'],
        ['day' => 'sun', 'time' => '10:00', 'title_el' => 'Ημέρα Δεύτερη · Καφές & κατάσταση κοινοτήτων', 'title_en' => 'Day Two · Coffee & State of the Communities', 'speaker' => 'Organising Committee', 'room' => 'Room 1', 'tracks' => ['community'], 'lang' => 'GR'],
        ['day' => 'sun', 'time' => '10:30', 'title_el' => 'RISC-V στις ελληνικές αίθουσες — Ένας χρόνος μετά', 'title_en' => 'RISC-V in Greek Classrooms — A Year In', 'speaker' => 'Y. Mavridis', 'room' => 'Room 3', 'tracks' => ['open-hardware', 'education'], 'lang' => 'GR'],
        ['day' => 'sun', 'time' => '13:30', 'title_el' => 'Lightning Talks · Block II', 'title_en' => 'Lightning Talks · Block II', 'speaker' => '8 speakers · 5 min each', 'room' => 'Room 1', 'tracks' => ['community'], 'lang' => 'GR/EN'],
        ['day' => 'sun', 'time' => '16:00', 'title_el' => 'Closing Panel · Τα επόμενα δεκαοκτώ χρόνια', 'title_en' => 'Closing Panel · The Next Eighteen Years', 'speaker' => 'Past organisers, in conversation', 'room' => 'Room 1', 'tracks' => ['community'], 'lang' => 'GR'],
    ]);

    fc_seed_if_empty('fc_section_venue', [
        'title_el'   => 'Χώρος',
        'title_en'   => 'Venue',
        'university_title_el' => 'Εθνικό Μετσόβιο Πολυτεχνείο',
        'university_title_en' => 'National Technical University of Athens',
        'hover_text'          => '37.9838°N, 23.7275°E',
        'google_maps_url'     => 'https://www.google.com/maps/search/?api=1&query=National+Technical+University+of+Athens',
        'address_el'          => "Ηρώων Πολυτεχνείου 9\nΖωγράφου 157 80\nΑθήνα, Ελλάδα",
        'address_en'          => "Iroon Polytechniou 9\nZografou 157 80\nAthens, Greece",
        'info_rows'           => [
            ['label_el' => 'Χωρητικότητα', 'label_en' => 'Capacity',         'value_el' => '~800',                 'value_en' => '~800'],
            ['label_el' => 'Αίθουσες',     'label_en' => 'Rooms',            'value_el' => '4',                    'value_en' => '4'],
            ['label_el' => 'Μετρό',        'label_en' => 'Transit',          'value_el' => 'Κατεχάκη (Γρ. 3)',     'value_en' => 'Katehaki (Line 3)'],
            ['label_el' => 'Προσβασιμότητα','label_en' => 'Access',          'value_el' => 'Ράμπες · Ασανσέρ',     'value_en' => 'Ramps · Lifts'],
        ],
        'cluster_label' => 'FOSSCOMM',
        'editions' => [
            ['year' => 2008, 'city' => 'Athens',       'lat' => '37.9838', 'lon' => '23.7275', 'url' => ''],
            ['year' => 2009, 'city' => 'Larissa',      'lat' => '39.6390', 'lon' => '22.4191', 'url' => ''],
            ['year' => 2010, 'city' => 'Thessaloniki', 'lat' => '40.6401', 'lon' => '22.9444', 'url' => ''],
            ['year' => 2011, 'city' => 'Patras',       'lat' => '38.2466', 'lon' => '21.7346', 'url' => ''],
            ['year' => 2012, 'city' => 'Athens',       'lat' => '37.9838', 'lon' => '23.7275', 'url' => ''],
            ['year' => 2013, 'city' => 'Heraklion',    'lat' => '35.3387', 'lon' => '25.1442', 'url' => ''],
            ['year' => 2014, 'city' => 'Lamia',        'lat' => '38.8991', 'lon' => '22.4340', 'url' => ''],
            ['year' => 2015, 'city' => 'Athens',       'lat' => '37.9838', 'lon' => '23.7275', 'url' => ''],
            ['year' => 2016, 'city' => 'Thessaloniki', 'lat' => '40.6401', 'lon' => '22.9444', 'url' => ''],
            ['year' => 2017, 'city' => 'Syros',        'lat' => '37.4438', 'lon' => '24.9211', 'url' => ''],
            ['year' => 2018, 'city' => 'Heraklion',    'lat' => '35.3387', 'lon' => '25.1442', 'url' => ''],
            ['year' => 2019, 'city' => 'Lamia',        'lat' => '38.8991', 'lon' => '22.4340', 'url' => ''],
            ['year' => 2020, 'city' => 'Online',       'lat' => '37.9838', 'lon' => '23.7275', 'url' => ''],
            ['year' => 2021, 'city' => 'Online',       'lat' => '37.9838', 'lon' => '23.7275', 'url' => ''],
            ['year' => 2022, 'city' => 'Athens',       'lat' => '37.9838', 'lon' => '23.7275', 'url' => ''],
            ['year' => 2023, 'city' => 'Heraklion',    'lat' => '35.3387', 'lon' => '25.1442', 'url' => ''],
            ['year' => 2024, 'city' => 'Piraeus',      'lat' => '37.9475', 'lon' => '23.6469', 'url' => 'https://2024.fosscomm.gr'],
            ['year' => 2025, 'city' => 'Thessaloniki', 'lat' => '40.6401', 'lon' => '22.9444', 'url' => 'https://2025.fosscomm.gr'],
            ['year' => 2026, 'city' => 'Athens',       'lat' => '37.9838', 'lon' => '23.7275', 'url' => '', 'spotlight' => true],
        ],
    ]);

    fc_seed_if_empty('fc_sponsors', [
        ['name' => 'GFOSS',                    'tier' => 'diamond'],
        ['name' => 'Open Technologies',        'tier' => 'diamond'],
        ['name' => 'Hellenic Telecom Lab',     'tier' => 'gold'],
        ['name' => 'Athena RC',                'tier' => 'gold'],
        ['name' => 'Crete Cloud',              'tier' => 'gold'],
        ['name' => 'Polytechnic Press',        'tier' => 'silver'],
        ['name' => 'kernel.gr',                'tier' => 'silver'],
        ['name' => 'OpenISP',                  'tier' => 'silver'],
        ['name' => 'GreekNOG',                 'tier' => 'silver'],
        ['name' => 'DataCo-op',                'tier' => 'silver'],
        ['name' => 'Ubuntu-gr',                'tier' => 'community'],
        ['name' => 'Fedora Greece',            'tier' => 'community'],
        ['name' => 'Debian Hellas',            'tier' => 'community'],
        ['name' => 'hsgr',                     'tier' => 'community'],
        ['name' => 'HELLUG',                   'tier' => 'community'],
        ['name' => 'P2P Lab',                  'tier' => 'community'],
        ['name' => 'Mozilla GR',               'tier' => 'community'],
        ['name' => 'OW2',                      'tier' => 'community'],
        ['name' => 'OpenStreetMap GR',         'tier' => 'community'],
        ['name' => 'KDE Greece',               'tier' => 'community'],
        ['name' => 'GNOME Hellas',             'tier' => 'community'],
        ['name' => 'Hackerspace.gr',           'tier' => 'in-kind'],
    ]);

    // Past Editions is no longer a standalone section — its data lives in the Venue
    // section's editions repeater (seeded above in fc_section_venue.editions).

    fc_seed_if_empty('fc_section_volunteer', [
        'title_el' => 'Το συνέδριο είναι οι εθελοντές του.',
        'title_en' => 'The conference is its volunteers.',
        'intro_el' => '',
        'intro_en' => '',
        'cfp_title_el' => 'Υπέβαλε μια ομιλία. Ή ένα workshop. Ή και τα δύο.',
        'cfp_title_en' => 'Submit a talk. Or a workshop. Or both.',
        'cfp_body_el'  => "Η πρόσκληση κλείνει στις 31 Ιουλίου 2026. Δεχόμαστε ομιλίες (25′ + 5′ Q&A), workshops (1–2.5 ώρες), lightning talks (5′), και panels.\n\nΜπορείς να υποβάλεις στα Ελληνικά ή στα Αγγλικά. Ενθαρρύνουμε όσους θα ομιλήσουν για πρώτη φορά.",
        'cfp_body_en'  => "The CFP closes on 31 July 2026. We accept talks (25 min + 5 Q&A), workshops (1–2.5 h), lightning talks (5 min), and panels.\n\nYou can submit in Greek or English. We strongly encourage first-time speakers.",
        'cfp_deadline' => '2026-07-31T23:59',
        'fund_goal'    => 8000,
        'fund_raised'  => 5200,
        'cards' => fc_default_volunteer_cards(),
    ]);

    fc_seed_if_empty('fc_section_footer', [
        'col1_title_el' => 'Επικοινωνία',
        'col1_title_en' => 'Contact',
        'col1_body_el'  => '',
        'col1_body_en'  => '',
        'col1_links'    => [
            ['label_el' => 'Email',    'label_en' => 'Email',    'url' => 'mailto:hello@fosscomm.gr'],
            ['label_el' => 'Mastodon', 'label_en' => 'Mastodon', 'url' => 'https://mastodon.social/@fosscomm'],
            ['label_el' => 'GitHub',   'label_en' => 'GitHub',   'url' => 'https://github.com/fosscomm'],
            ['label_el' => 'Matrix',   'label_en' => 'Matrix',   'url' => 'https://matrix.to/#/#fosscomm:matrix.org'],
        ],
        'col2_title_el' => 'Αδελφές κοινότητες',
        'col2_title_en' => 'Sister communities',
        'col2_body_el'  => '',
        'col2_body_en'  => '',
        'col2_links'    => [
            ['label_el' => 'GFOSS',     'label_en' => 'GFOSS',     'url' => 'https://gfoss.eu'],
            ['label_el' => 'Ubuntu-gr', 'label_en' => 'Ubuntu-gr', 'url' => 'https://ubuntu-gr.org'],
            ['label_el' => 'Fedora Greece', 'label_en' => 'Fedora Greece', 'url' => 'https://fedoraproject.org'],
            ['label_el' => 'HELLUG',    'label_en' => 'HELLUG',    'url' => 'https://www.hellug.gr'],
            ['label_el' => 'hsgr',      'label_en' => 'hsgr',      'url' => 'https://www.hackerspace.gr'],
        ],
        'col3_title_el' => '',
        'col3_title_en' => '',
        'col3_body_el'  => '',
        'col3_body_en'  => '',
        'col3_links'    => [],
    ]);

    fc_seed_if_empty('fc_section_conduct', [
        'title_el' => 'Κώδικας Συμπεριφοράς',
        'title_en' => 'Code of Conduct',
        'body_el'  => "Το FOSSCOMM είναι ένας χώρος όπου όλοι οι συμμετέχοντες — ομιλητές, εθελοντές, χορηγοί και επισκέπτες — αντιμετωπίζονται με σεβασμό και αξιοπρέπεια.\n\nΔεν ανεχόμαστε παρενόχληση σε καμία μορφή: σχόλια που στοχοποιούν φύλο, ταυτότητα φύλου, σεξουαλικό προσανατολισμό, αναπηρία, εμφάνιση, σώμα, εθνικότητα, ηλικία ή θρησκεία· εκφοβισμό, διαδικτυακό ή διά ζώσης· ανεπιθύμητη φωτογράφιση ή ηχογράφηση· συνεχιζόμενη διακοπή ομιλιών και workshops.\n\nΟι διοργανωτές διατηρούν το δικαίωμα να αφαιρέσουν περιεχόμενο, να αρνηθούν είσοδο ή να αποβάλουν συμμετέχοντες χωρίς προειδοποίηση ή επιστροφή χρημάτων.\n\nΑν αντιμετωπίσεις ή γίνεις μάρτυρας περιστατικού, παρακαλούμε επικοινώνησε με ένα μέλος της οργανωτικής επιτροπής (κίτρινο μπλουζάκι) ή στείλε email στο [conduct@fosscomm.gr](mailto:conduct@fosscomm.gr). Όλες οι αναφορές αντιμετωπίζονται εμπιστευτικά.",
        'body_en'  => "FOSSCOMM is a space where everyone — speakers, volunteers, sponsors and attendees — is treated with respect and dignity.\n\nWe do not tolerate harassment in any form: comments targeting gender, gender identity, sexual orientation, disability, appearance, body, ethnicity, age or religion; intimidation, online or in person; unwanted photography or recording; sustained disruption of talks and workshops.\n\nOrganisers reserve the right to remove content, deny entry, or expel participants without warning or refund.\n\nIf you experience or witness an incident, please contact a member of the organising committee (yellow shirt) or email [conduct@fosscomm.gr](mailto:conduct@fosscomm.gr). All reports are handled confidentially.\n\nThis Code of Conduct is adapted from the [Contributor Covenant](https://www.contributor-covenant.org/) and applies to all event venues, online channels and side-events under the FOSSCOMM banner.",
    ]);

    fc_seed_if_empty('fc_faq', [
        ['question_el' => 'Είναι πραγματικά δωρεάν το συνέδριο;',
         'question_en' => 'Is the conference really free?',
         'answer_el'   => 'Ναι. Το FOSSCOMM είναι δωρεάν από το 2008. Δωρεές και χορηγοί καλύπτουν τα έξοδα.',
         'answer_en'   => 'Yes. FOSSCOMM has been free since 2008. Donations and sponsors cover the venue.'],
        ['question_el' => 'Χρειάζεται εγγραφή;',
         'question_en' => 'Do I need to register?',
         'answer_el'   => 'Η εγγραφή μας βοηθάει να μετρήσουμε καρέκλες και καφέ. Σύνδεσμος εδώ στο καλοκαίρι 2026.',
         'answer_en'   => 'Registration helps us count chairs and coffee. A link will appear here in late summer 2026.'],
        ['question_el' => 'Μπορώ να παρακολουθήσω εξ αποστάσεως;',
         'question_en' => 'Can I attend remotely?',
         'answer_el'   => 'Οι ομιλίες στην κεντρική αίθουσα μεταδίδονται live και αρχειοθετούνται. Τα workshops μόνο δια ζώσης.',
         'answer_en'   => 'Talks in the Main Hall are streamed live and archived afterward. Workshops are in-person only.'],
        ['question_el' => 'Θέλω να γίνω εθελοντής/τρια.',
         'question_en' => 'I want to volunteer.',
         'answer_el'   => 'Παρακαλούμε. Δες την ενότητα [Πάρε Μέρος](#volunteer). Χρειαζόμαστε βοήθεια σε μετάφραση, streaming, σήμανση και φιλοξενία.',
         'answer_en'   => 'Please. See the [Get Involved](#volunteer) section. We need help with translation, streaming, signage, and hospitality.'],
    ]);

    fc_migrate_past_editions_into_venue();
    fc_migrate_clear_default_signature();
    fc_migrate_volunteer_cards();
    fc_migrate_clear_default_colophon();
}

/**
 * One-time migration: blank out the old "Colophon" defaults in the footer's
 * third column if they're still untouched from the previous seed. User-edited
 * column 3 content is preserved.
 */
function fc_migrate_clear_default_colophon(): void {
    $footer = get_option('fc_section_footer', null);
    if (!is_array($footer)) return;
    $title_en = (string) ($footer['col3_title_en'] ?? '');
    $title_el = (string) ($footer['col3_title_el'] ?? '');
    $body_en  = (string) ($footer['col3_body_en']  ?? '');
    $body_el  = (string) ($footer['col3_body_el']  ?? '');
    $old_default_titles = ($title_en === 'Colophon' && $title_el === 'Colophon');
    $old_default_body_en = strpos($body_en, 'ASCII generated at runtime') !== false;
    $old_default_body_el = strpos($body_el, 'ASCII στο runtime') !== false;
    if (!$old_default_titles && !$old_default_body_en && !$old_default_body_el) return;
    if ($old_default_titles) { $footer['col3_title_en'] = ''; $footer['col3_title_el'] = ''; }
    if ($old_default_body_en) $footer['col3_body_en'] = '';
    if ($old_default_body_el) $footer['col3_body_el'] = '';
    update_option('fc_section_footer', $footer, false);
}

/**
 * Canonical default Get Involved cards. Shared by the seed and the migration
 * so both stay in sync. Each card: bilingual title, link, bilingual description.
 */
function fc_default_volunteer_cards(): array {
    return [
        [
            'title_en' => 'Participate', 'title_el' => 'Συμμετείχε', 'url' => '#schedule',
            'body_en'  => 'Two days of talks, workshops and lightning sessions. Register and just show up.',
            'body_el'  => 'Δύο μέρες ομιλιών, workshops και lightning talks. Δήλωσε συμμετοχή και έλα.',
        ],
        [
            'title_en' => 'Sponsor', 'title_el' => 'Χορήγησε', 'url' => '#sponsors',
            'body_en'  => 'Back a volunteer-run, free conference and help keep it independent.',
            'body_el'  => 'Στήριξε ένα εθελοντικό, δωρεάν συνέδριο και βοήθησε να μείνει ανεξάρτητο.',
        ],
        [
            'title_en' => 'Volunteer', 'title_el' => 'Προσέφερε', 'url' => '#',
            'body_en'  => 'Translate, run a booth, staff the desk, or donate compute. Many hands.',
            'body_el'  => 'Μετάφρασε, στήσε booth, βοήθησε στη γραμματεία ή δώρισε υπολογιστική ισχύ.',
        ],
    ];
}

/**
 * One-time migration: replace the old default Get Involved cards
 * (Translate / Run a booth / Donate compute) with the new
 * Participate / Sponsor / Volunteer set, and drop the removed
 * contact_email field. Only fires when the stored cards are still the
 * untouched old defaults (or empty) — user-renamed cards are never
 * clobbered. Idempotent: after running, the titles no longer match the
 * old set, so it never runs again.
 */
function fc_migrate_volunteer_cards(): void {
    $vol = get_option('fc_section_volunteer', null);
    if (!is_array($vol)) return;

    $cards = isset($vol['cards']) && is_array($vol['cards']) ? $vol['cards'] : [];

    $is_old_default = empty($cards);
    if (!$is_old_default) {
        $titles = array_map(function ($c) {
            return is_array($c) ? (string) ($c['title_en'] ?? '') : '';
        }, array_values($cards));
        sort($titles);
        $old = ['Donate compute', 'Run a booth', 'Translate']; // sorted
        $is_old_default = ($titles === $old);
    }
    if (!$is_old_default) return;

    $vol['cards'] = fc_default_volunteer_cards();
    unset($vol['contact_email']); // legacy field, no longer used
    update_option('fc_section_volunteer', $vol, false);
}

/**
 * One-time migration: fold the now-removed standalone Past Editions section
 * (fc_past_editions) into the Venue section's editions repeater. Only runs if the
 * user has Past Editions content but no editions saved on the Venue section yet —
 * user-managed Venue editions are never clobbered. Idempotent; safe every activation.
 */
function fc_migrate_past_editions_into_venue(): void {
    $past = get_option('fc_past_editions', null);
    if (!is_array($past) || empty($past)) return;

    $venue = get_option('fc_section_venue', []);
    if (!is_array($venue)) $venue = [];

    if (!empty($venue['editions']) && is_array($venue['editions'])) return;

    $editions = [];
    foreach ($past as $row) {
        if (!is_array($row) || empty($row['year'])) continue;
        $editions[] = [
            'year'      => (int)    $row['year'],
            'city'      => (string) ($row['city'] ?? ''),
            'lat'       => (string) ($row['lat'] ?? ''),
            'lon'       => (string) ($row['lon'] ?? ''),
            'url'       => (string) ($row['url'] ?? ''),
            'spotlight' => !empty($row['current']) || !empty($row['spotlight']),
        ];
    }
    if (empty($editions)) return;

    $venue['editions'] = $editions;
    update_option('fc_section_venue', $venue, false);
    // fc_past_editions is left in place (no destructive deletes); it is simply no
    // longer read once the Venue section has its own editions list.
}

/**
 * One-time migration: clear the seeded FOSSCOMM ASCII signature in the footer so existing
 * installs don't keep showing it at the bottom. Only fires if the stored value matches the
 * old default — user-customized signatures are preserved.
 */
function fc_migrate_clear_default_signature(): void {
    $footer = get_option('fc_section_footer', null);
    if (!is_array($footer) || empty($footer['signature'])) return;
    if (strpos($footer['signature'], '╔═╗╔═╗╔═╗╔═╗╔═╗╔═╗') !== false) {
        $footer['signature'] = '';
        update_option('fc_section_footer', $footer, false);
    }
}

function fc_seed_if_empty(string $option_key, $default_value): void {
    $existing = get_option($option_key, null);
    if ($existing === null || $existing === '' || (is_array($existing) && empty($existing))) {
        update_option($option_key, $default_value, false);
        return;
    }
    // Recovery: if every leaf in the stored array is an empty string, treat it as blank
    // and refill from defaults. This handles options corrupted by the pre-fix save bug.
    if (is_array($existing) && fc_array_is_blank($existing)) {
        update_option($option_key, $default_value, false);
    }
}

function fc_array_is_blank(array $a): bool {
    if (empty($a)) return true;
    foreach ($a as $v) {
        if (is_array($v)) {
            if (!fc_array_is_blank($v)) return false;
            continue;
        }
        if ($v === '' || $v === null) continue;
        if (is_bool($v) && $v === false) continue;
        return false;
    }
    return true;
}
