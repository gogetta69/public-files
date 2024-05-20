const puppeteer = require('puppeteer-core');
const path = require('path');
const fs = require('fs-extra');
const os = require('os');
const isPkg = typeof process.pkg !== 'undefined';

// Determine the base path of the script.### DO NOT CHANGE THIS LINE!!! ###
const basePath = isPkg ? path.dirname(process.execPath) : path.resolve(__dirname);

// Determine the operating system
const platform = os.platform();

let customChromiumPath;
let executablePath;

if (platform === 'win32') {
    customChromiumPath = path.join(basePath, 'chromium', 'chrome-win64');
    executablePath = path.join(customChromiumPath, 'chrome.exe');
} else if (platform === 'linux') {
    customChromiumPath = path.join(basePath, 'chromium', 'chrome-linux');
    executablePath = path.join(customChromiumPath, 'chrome');
} else {
    console.error('Unsupported OS');
    process.exit(1);
}

//console.log(`Custom Chromium Path: ${customChromiumPath}`);
//console.log(`Executable Path: ${executablePath}`);

async function run(TV_URL) {
    // Ensure the custom Chromium path exists
    if (!fs.existsSync(executablePath)) {
        console.error('Chromium is not downloaded or executable not found. Please check the path.');
        process.exit(1);
    }

    const browser = await puppeteer.launch({
        headless: true, // Now running in headless mode
        executablePath, // Use the resolved executable path
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-accelerated-2d-canvas',
            '--disable-gpu'
        ]
    });

    const page = await browser.newPage();

    // Set custom headers
    await page.setExtraHTTPHeaders({
        "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
        "User-Agent": "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0 Safari/605.1.15",
        "Accept-Language": "en-US,en;q=0.5",
        "Referer": "https://thetvapp.to/",
        "Connection": "keep-alive",
        "Upgrade-Insecure-Requests": "1",
        "Sec-Fetch-Des": "document",
        "Sec-Fetch-Mode": "navigate",
        "Sec-Fetch-Site": "same-origin",
        "Sec-Fetch-User": "?1",
        "Pragma": "no-cache",
        "Cache-Control": "no-cache"
    });

    await page.setViewport({ width: 1280, height: 800 });

    let selectedUrl = false;

    page.on('request', interceptedRequest => {
        const url = interceptedRequest.url();
        if (url.includes('token=') && url.includes('expires=')) {
            selectedUrl = url;
            //console.log(`Selected URL: ${url}`); // Log the selected URL
            page.removeAllListeners('request'); // Stop intercepting further requests
        }
    });

    try {
        await page.goto(TV_URL, { waitUntil: 'networkidle0' });
    } catch (error) {
        console.error('Error loading page:', error);
    }

    await browser.close();

    // Output the selected URL in JSON format
    console.log(JSON.stringify({ url: selectedUrl ? selectedUrl : false }));
}

// Get the URL from the command line arguments
const TV_URL = process.argv[2];
if (!TV_URL) {
    console.error('Please provide the TV URL as an argument.');
    process.exit(1);
}

run(TV_URL);
