<?php

namespace AssistantKit\Tests;

use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use PortScaffolder;

require_once dirname(__DIR__) . '/tools/PortScaffolder.php';

/**
 * A generated port is only useful if it loads and runs. These tests take the
 * shipped example, write the files, lint them, and drive them against a real
 * sqlite schema — so the promise holds: marked facts answer, unmarked ones stay
 * silent, and the SQL identifiers survive.
 */
final class PortScaffoldTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->tempFiles = [];
    }

    /** @return array<string, mixed> */
    private function spec(): array
    {
        $json = json_decode((string) file_get_contents(dirname(__DIR__) . '/scaffold/catalogue.example.json'), true);

        $this->assertIsArray($json, 'the shipped scaffold example must be valid JSON');

        return $json;
    }

    private function writeTemp(string $name, string $contents): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $name;
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }

    private function lint(string $path): string
    {
        return (string) shell_exec('php -l ' . escapeshellarg($path) . ' 2>&1');
    }

    public function test_the_generated_files_are_valid_php(): void
    {
        $generated = PortScaffolder::fromSpec($this->spec());

        foreach (['catalogue' => 'FurnitureCatalogue', 'leads' => 'FurnitureLeads'] as $key => $class) {
            $this->assertStringStartsWith('<?php', $generated[$key]);
            $path = $this->writeTemp("{$class}-lint.php", $generated[$key]);
            $this->assertStringContainsString('No syntax errors', $this->lint($path), "generated {$key} did not lint");
        }
    }

    public function test_only_marked_columns_become_answerable_facts(): void
    {
        $catalogue = PortScaffolder::fromSpec($this->spec())['catalogue'];

        $this->assertStringContainsString("\$facts['price'] = sprintf('The price is %s.'", $catalogue);
        $this->assertStringContainsString("\$facts['dimensions']", $catalogue);
        $this->assertStringNotContainsString("\$facts['material']", $catalogue);
        $this->assertStringNotContainsString("\$facts['delivery']", $catalogue);
        $this->assertStringContainsString('// material:', $catalogue, 'an unwired fact should be left as a comment');
    }

    public function test_the_generated_catalogue_reads_and_filters_a_real_table(): void
    {
        $generated = PortScaffolder::fromSpec($this->spec());
        $path = $this->writeTemp('FurnitureCatalogue-run.php', $generated['catalogue']);
        require_once $path;

        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE products (slug TEXT PRIMARY KEY, name TEXT, url TEXT, category TEXT, city TEXT, price INTEGER, dimensions TEXT)');
        $insert = $pdo->prepare('INSERT INTO products (slug, name, url, category, city, price, dimensions) VALUES (?,?,?,?,?,?,?)');
        $insert->execute(['sofa-x', 'Corner Sofa', 'https://shop/sofa-x', 'sofa', 'Dubai', 2500, '220cm']);
        $insert->execute(['lamp-y', 'Floor Lamp', 'https://shop/lamp-y', 'lamp', 'Dubai', 900, '150cm']);

        $catalogue = new \FurnitureCatalogue($pdo);

        $results = $catalogue->search(['type' => 'sofa', 'price_max' => 3000], 10);
        $this->assertSame(1, $results['total']);
        $this->assertSame('sofa-x', $results['items'][0]['slug']);
        $this->assertSame('Corner Sofa', $results['items'][0]['title']);

        $found = $catalogue->find('sofa-x');
        $this->assertIsArray($found);

        $facts = $catalogue->facts((array) $found);
        $this->assertSame('The price is 2500.', $facts['price']);
        $this->assertSame('It measures 220cm.', $facts['dimensions']);
        $this->assertArrayNotHasKey('material', $facts);
        $this->assertNull($catalogue->find('nope'));
    }

    public function test_the_generated_lead_sink_writes_into_the_site_table(): void
    {
        $generated = PortScaffolder::fromSpec($this->spec());
        $path = $this->writeTemp('FurnitureLeads-run.php', $generated['leads']);
        require_once $path;

        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE enquiries (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT, phone TEXT, notes TEXT, product_id TEXT, source TEXT, chat_id TEXT)');

        $ref = (new \FurnitureLeads($pdo))->capture([
            'name' => 'Sara',
            'email' => 'sara@example.com',
            'message' => 'is this in stock?',
            'item' => 'sofa-x',
            'chat' => 'chat-7',
        ]);

        $row = $pdo->query('SELECT * FROM enquiries')->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('sara@example.com', $row['email']);
        $this->assertSame('sofa-x', $row['product_id']);
        $this->assertSame('chat', $row['source']);
        $this->assertSame((string) $row['id'], $ref);
    }

    public function test_a_missing_leads_block_defers_to_the_shipped_sink(): void
    {
        $spec = $this->spec();
        unset($spec['db']['leads']);

        $generated = PortScaffolder::fromSpec($spec);

        $this->assertStringContainsString('No "db.leads"', $generated['leads']);
    }

    public function test_unsafe_identifiers_are_refused(): void
    {
        $spec = $this->spec();
        $spec['db']['table'] = 'products; DROP TABLE users';

        $this->expectException(InvalidArgumentException::class);
        PortScaffolder::fromSpec($spec);
    }
}
