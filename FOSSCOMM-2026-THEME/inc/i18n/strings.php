<?php
/**
 * UI string dictionary for hard-coded chrome (buttons, labels, ARIA strings).
 * Editable copy lives in section options; this file is for strings that aren't worth a settings page.
 */
if (!defined('ABSPATH')) {
    exit;
}

function fc_strings(): array {
    static $dict = null;
    if ($dict !== null) {
        return $dict;
    }
    $dict = [
        'el' => [
            'not_found_message'   => 'Η σελίδα δεν βρέθηκε.',
            'back_home'           => 'Πίσω στην αρχική',
            'open_schedule'       => 'Άνοιξε το πρόγραμμα →',
            'submit_talk'         => 'Υπέβαλε ομιλία →',
            'volunteer'           => 'Γίνε εθελοντής/τρια →',
            'sat'                 => 'Σάββατο',
            'sun'                 => 'Κυριακή',
            'all_tracks'          => 'Όλες οι κατηγορίες',
            'before_workshop'     => 'Πριν το workshop',
            'read_full_coc'       => 'Διάβασε ολόκληρο τον Κώδικα →',
            'event_dates'         => 'Ημερομηνίες',
            'event_venue'         => 'Χώρος',
            'event_cost'          => 'Κόστος',
            'getting_here'        => 'Πώς θα έρθεις',
            'become_sponsor'      => 'Γίνε χορηγός →',
            'no_archive'          => 'χωρίς αρχείο',
            'you_are_here'        => '· βρίσκεσαι εδώ',
            'lang_switch_label'   => 'Γλώσσα',
            'sections_nav_label'  => 'Ενότητες',
            'editions_label'      => 'ΔΙΟΡΓΑΝΩΣΕΙΣ',
            'past_editions_label' => 'ΠΡΟΗΓΟΥΜΕΝΕΣ ΔΙΟΡΓΑΝΩΣΕΙΣ',
            'cfp_closes_in'       => 'ΟΙ ΥΠΟΒΟΛΕΣ ΚΛΕΙΝΟΥΝ ΣΕ',
            'cfp_closed'          => 'ΟΙ ΥΠΟΒΟΛΕΣ ΕΚΛΕΙΣΑΝ',
            'funding_goal'        => 'ΣΤΟΧΟΣ ΧΡΗΜΑΤΟΔΟΤΗΣΗΣ',
            'funding_reached'     => 'Ο ΣΤΟΧΟΣ ΕΠΙΤΕΥΧΘΗΚΕ',
        ],
        'en' => [
            'not_found_message'   => 'Page not found.',
            'back_home'           => 'Back home',
            'open_schedule'       => 'Open the schedule →',
            'submit_talk'         => 'Submit a talk →',
            'volunteer'           => 'Volunteer →',
            'sat'                 => 'Saturday',
            'sun'                 => 'Sunday',
            'all_tracks'          => 'All tracks',
            'before_workshop'     => 'Before the workshop',
            'read_full_coc'       => 'Read the full Code of Conduct →',
            'event_dates'         => 'Dates',
            'event_venue'         => 'Venue',
            'event_cost'          => 'Cost',
            'getting_here'        => 'Getting here',
            'become_sponsor'      => 'Become a sponsor →',
            'no_archive'          => 'no archive',
            'you_are_here'        => '· you are here',
            'lang_switch_label'   => 'Language',
            'sections_nav_label'  => 'Sections',
            'editions_label'      => 'EDITIONS',
            'past_editions_label' => 'PAST EDITIONS',
            'cfp_closes_in'       => 'SUBMISSIONS CLOSE IN',
            'cfp_closed'          => 'SUBMISSIONS CLOSED',
            'funding_goal'        => 'FUNDING GOAL',
            'funding_reached'     => 'GOAL REACHED',
        ],
    ];
    return $dict;
}

function fc_t(string $key, string $fallback = ''): string {
    $lang = fc_current_lang();
    $dict = fc_strings();
    return $dict[$lang][$key] ?? ($dict['en'][$key] ?? $fallback);
}
