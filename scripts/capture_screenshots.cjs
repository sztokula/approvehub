const fs = require('fs');
const path = require('path');

async function run() {
    let chromium;
    try {
        ({ chromium } = require('playwright'));
    } catch (error) {
        throw new Error('Missing dependency: playwright. Install it first with `npm i -D playwright`.');
    }

    const baseUrl = process.env.APPROVEHUB_BASE_URL || 'https://approvehub.test';
    const outputDir = path.join(process.cwd(), 'public', 'portfolio', 'screenshots');
    fs.mkdirSync(outputDir, { recursive: true });

    const email = `portfolio_${Date.now()}@example.com`;
    const password = 'Password123!';

    const browser = await chromium.launch({ headless: true });

    try {
        const page = await browser.newPage({ viewport: { width: 1440, height: 2000 } });

        await page.goto(`${baseUrl}/register`, { waitUntil: 'networkidle' });
        await page.fill('input[name="name"]', 'Portfolio User');
        await page.fill('input[name="email"]', email);
        await page.fill('input[name="organization_name"]', 'Portfolio Org');
        await page.fill('input[name="password"]', password);
        await page.fill('input[name="password_confirmation"]', password);
        await page.click('button[type="submit"]');

        await page.waitForURL('**/dashboard', { timeout: 30000 });
        await page.screenshot({ path: path.join(outputDir, 'dashboard.png'), fullPage: true });

        await page.goto(`${baseUrl}/documents`, { waitUntil: 'networkidle' });

        const newDocumentButton = page.locator('button:has-text("New Document")').first();
        if (await newDocumentButton.isVisible()) {
            await newDocumentButton.click();
        }

        await page.fill('input[name="title"]', 'Portfolio Workflow Document');
        await page.fill('input[name="description"]', 'Document used for portfolio screenshots');
        await page.selectOption('select[name="document_type"]', 'contract');
        await page.selectOption('select[name="visibility"]', 'organization');
        await page.fill('textarea[name="content"]', 'Line one\nLine two\nLine three');
        await page.click('form[action$="/documents"] button[type="submit"]');

        await page.waitForURL('**/documents/*', { timeout: 30000 });

        const templateSelect = page.locator('select[name="template_id"]');
        if ((await templateSelect.count()) > 0) {
            const options = await templateSelect.locator('option').count();
            if (options > 1) {
                await templateSelect.selectOption({ index: 1 });
                await page.click('button:has-text("Submit Review Workflow")');
                await page.waitForLoadState('networkidle');
            }
        }

        await page.screenshot({ path: path.join(outputDir, 'workflow.png'), fullPage: true });

        const historySection = page.locator('#section-versions summary').first();
        await historySection.scrollIntoViewIfNeeded();
        await historySection.click();
        await page.waitForTimeout(600);
        await page.screenshot({ path: path.join(outputDir, 'history.png'), fullPage: true });

        const auditSection = page.locator('#section-audit summary').first();
        await auditSection.scrollIntoViewIfNeeded();
        await auditSection.click();
        await page.waitForTimeout(600);
        await page.screenshot({ path: path.join(outputDir, 'audit.png'), fullPage: true });
    } finally {
        await browser.close();
    }
}

run().catch((error) => {
    console.error(error);
    process.exit(1);
});
