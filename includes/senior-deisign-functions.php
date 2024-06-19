<?php
// formats the publication types
function semester_category($number) {
    switch ($number) {
        case 330:
            return "Fall 2019";
        case 2:
            return "Book Chapters";
        case 3:
            return "Journal Papers (refereed)";
        case 4:
            return "Conference Proceedings";
        case 5:
            return "Other Unreferenced Publications";
        case 6:
            return "Patents";
        case 7:
            return "Pending Patents";
        case 8:
            return "Disclosures";
        case 9:
            return "Theses or Dissertations";
        case 10:
            return "News Coverage";
        case 11:
            return "Presentations";
        case 12:
            return "Invited Presentations";
        case 13:
            return "Plenary Presentations";
        case 14:
            return "Tutorials";
        case 15:
            return "Posters";
        case 16:
            return "Workshops";
        case 17:
            return "Seminar";
        default:
            return "Invalid Type";
    }
}