<?php
// if not defined, define DOKU_INC
if (!defined('DOKU_INC')) die();

// include the base syntax class
require_once(DOKU_PLUGIN . 'syntax.php');
require_once(__DIR__ . '/autoload.php'); // Include the autoloader

use Gedcom\Parser;

class syntax_plugin_gedcomviewer extends DokuWiki_Syntax_Plugin {

    function getType() {
        return 'substition';
    }

    function getSort() {
        return 989;
    }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('<gedcomviewer>.*?</gedcomviewer>', $mode, 'plugin_gedcomviewer');
    }

    function handle($match, $state, $pos, Doku_Handler $handler) {
        $file = trim(substr($match, 14, -15)); // Extract the file path from the match
        return array($file);
    }

    function render($mode, Doku_Renderer $renderer, $data) {
        if ($mode == 'xhtml') {
            $file = $data[0];
            $gedcom = $this->parseGedcom($file);
            $html = $this->generateHtmlTable($gedcom);
            $renderer->doc .= $html;
            return true;
        }
        return false;
    }

    private function parseGedcom($file) {
        $parser = new Parser();
        $gedcom = $parser->parse($file);
        return $gedcom;
    }

    private function generateHtmlTable($gedcom) {
        // JavaScript for sorting table columns and toggling RTL/LTR
        $script = <<<EOD
<script>
function sortTable(n) {
  var table, rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
  table = document.getElementById("gedcomTable");
  switching = true;
  dir = "asc"; 
  while (switching) {
    switching = false;
    rows = table.rows;
    for (i = 1; i < (rows.length - 1); i++) {
      shouldSwitch = false;
      x = rows[i].getElementsByTagName("TD")[n];
      y = rows[i + 1].getElementsByTagName("TD")[n];
      if (dir == "asc") {
        if (x.innerHTML.toLowerCase() > y.innerHTML.toLowerCase()) {
          shouldSwitch= true;
          break;
        }
      } else if (dir == "desc") {
        if (x.innerHTML.toLowerCase() < y.innerHTML.toLowerCase()) {
          shouldSwitch= true;
          break;
        }
      }
    }
    if (shouldSwitch) {
      rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
      switching = true;
      switchcount ++; 
    } else {
      if (switchcount == 0 && dir == "asc") {
        dir = "desc";
        switching = true;
      }
    }
  }
}

function toggleDirection(direction) {
  var content = document.getElementById("gedcomContent");
  content.setAttribute("dir", direction);
}
</script>
EOD;

        // HTML table with sortable headers and direction toggle buttons
        $html = '<div id="gedcomContent" dir="LTR">';
        $html .= '<button onclick="toggleDirection(\'RTL\')">Align Right - RTL</button>';
        $html .= '<button onclick="toggleDirection(\'LTR\')">Align Left - LTR</button>';
        $html .= '<table id="gedcomTable" border="1">';
        $html .= '<tr>
                    <th>ID <button onclick="sortTable(0)">SORT</button></th>
                    <th>Given Name <button onclick="sortTable(1)">SORT</button></th>
                    <th>Surname <button onclick="sortTable(2)">SORT</button></th>
                    <th>Birth Date <button onclick="sortTable(3)">SORT</button></th>
                    <th>Death Date <button onclick="sortTable(4)">SORT</button></th>
                  </tr>';

        foreach ($gedcom->getIndi() as $individual) {
            $id = $individual->getId();
            $names = $individual->getName();
            $name = reset($names);
            $givenName = $name->getGivn();
            $surname = $name->getSurn();
            $birthDate = $this->getEventDate($individual, 'BIRT');
            $deathDate = $this->getEventDate($individual, 'DEAT');

            $html .= "<tr>
                        <td>{$id}</td>
                        <td>{$givenName}</td>
                        <td>{$surname}</td>
                        <td>{$birthDate}</td>
                        <td>{$deathDate}</td>
                      </tr>";
        }

        $html .= '</table>';
        $html .= '</div>';
        return $script . $html; // Include the script in the HTML output
    }

    private function getEventDate($individual, $eventType) {
        $events = $individual->getEven($eventType);
        if (is_array($events) && !empty($events)) {
            $event = reset($events);
            return $event->getDate();
        }
        return 'N/A';
    }
}
?>
