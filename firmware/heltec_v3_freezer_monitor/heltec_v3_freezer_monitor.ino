#include "LoRaWan_APP.h"

// DS18B20
#define ONEWIRE_NO_DIRECT_GPIO
#include <OneWire.h>
#include <DallasTemperature.h>

// OLED Heltec V3
#include "HT_SSD1306Wire.h"

#include <math.h>

// -------------------- Config --------------------
const char* DEVICE_NAME = "Teste Pizzaria";

const int ONE_WIRE_PIN = 4;
const int DOOR_PIN = 7;               // Reed switch/contact sensor pin
const uint8_t DOOR_OPEN_VALUE = HIGH; // INPUT_PULLUP: HIGH=open, LOW=closed

const unsigned long measurementInterval = 300000UL; // 5 min safety heartbeat
const unsigned long DOOR_DEBOUNCE_MS = 300UL;
const unsigned long DOOR_POLL_INTERVAL_MS = 100UL;
unsigned long lastMeasure = 0;
unsigned long lastDoorPollAtMs = 0;
unsigned long doorSampleChangedAtMs = 0;

// -------------------- LoRaWAN OTAA --------------------
uint8_t devEui[] = { 0x92, 0x4a, 0x7f, 0x78, 0xd9, 0xcf, 0x8d, 0xbf };
uint8_t appEui[] = { 0xd3, 0xc2, 0xd9, 0x81, 0xb0, 0xdc, 0x99, 0x29 };
uint8_t appKey[] = { 0x7b, 0x0d, 0x02, 0x36, 0xfb, 0x6a, 0x2a, 0x89, 0xcd, 0x0d, 0x56, 0xcb, 0x3e, 0x0d, 0x91, 0xb2 };

uint8_t nwkSKey[] = {0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00};
uint8_t appSKey[] = {0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00};
uint32_t devAddr = 0x00000000;

uint16_t userChannelsMask[6] = { 0x00FF, 0x0000, 0x0000, 0x0000, 0x0000, 0x0000 };

// -------------------- LoRaWAN Settings --------------------
LoRaMacRegion_t loraWanRegion = LORAMAC_REGION_EU868;
DeviceClass_t   loraWanClass  = CLASS_A;

// Keep the LoRaWAN cycle short so door state changes are picked up quickly.
// Actual uplink frequency is still controlled by buildPayload().
uint32_t appTxDutyCycle = 30000UL;
bool overTheAirActivation = true;
bool loraWanAdr = true;
bool isTxConfirmed = false;
uint8_t appPort = 2;
uint8_t confirmedNbTrials = 3;

// -------------------- Peripherals --------------------
OneWire oneWire(ONE_WIRE_PIN);
DallasTemperature ds18b20(&oneWire);

SSD1306Wire factory_display(0x3c, 500000, SDA_OLED, SCL_OLED, GEOMETRY_128_64, RST_OLED);

static float lastTempC = NAN;
static int16_t lastTempRaw = (int16_t)0x8000; // sentinel for invalid/unavailable
static bool stableDoorOpen = true;
static bool rawDoorSample = true;
bool forceImmediateUplink = false;

// -------------------- Functions --------------------
void keepOLEDOn() {
  pinMode(Vext, OUTPUT);
  digitalWrite(Vext, LOW);   // LOW = OLED on
}

bool readDoorOpen() {
  return digitalRead(DOOR_PIN) == DOOR_OPEN_VALUE;
}

bool readDoorOpenStable() {
  uint8_t openCount = 0;
  const uint8_t samples = 7;

  for (uint8_t i = 0; i < samples; i++) {
    if (readDoorOpen()) {
      openCount++;
    }
    delay(2);
  }

  return openCount >= 4;
}

void oledDraw(float tempC, bool doorOpen) {
  keepOLEDOn();

  factory_display.clear();
  factory_display.setTextAlignment(TEXT_ALIGN_LEFT);
  factory_display.setFont(ArialMT_Plain_10);
  factory_display.drawString(0, 0, DEVICE_NAME);

  factory_display.setFont(ArialMT_Plain_24);
  if (isnan(tempC)) {
    factory_display.drawString(0, 18, "--.- C");
  } else {
    char buf[20];
    snprintf(buf, sizeof(buf), "%.2f C", tempC);
    factory_display.drawString(0, 18, buf);
  }

  factory_display.setFont(ArialMT_Plain_10);
  factory_display.drawString(0, 50, doorOpen ? "Door: OPEN" : "Door: CLOSED");
  factory_display.display();
}

void measureTemperature() {
  ds18b20.requestTemperatures();
  float tempC = ds18b20.getTempCByIndex(0);

  if (tempC <= -127.0f || tempC >= 85.0f) {
    Serial.println("DS18B20: read error");
    lastTempC = NAN;
    lastTempRaw = (int16_t)0x8000;
    return;
  }

  lastTempC = tempC;
  lastTempRaw = (int16_t)lroundf(tempC * 100.0f);
}

void handleDoorChangeEvent() {
  unsigned long now = millis();
  if (now - lastDoorPollAtMs < DOOR_POLL_INTERVAL_MS) {
    return;
  }
  lastDoorPollAtMs = now;

  bool currentSample = readDoorOpen();

  if (currentSample != rawDoorSample) {
    rawDoorSample = currentSample;
    doorSampleChangedAtMs = now;
    return;
  }

  if (currentSample == stableDoorOpen || now - doorSampleChangedAtMs < DOOR_DEBOUNCE_MS) {
    return;
  }

  stableDoorOpen = currentSample;
  forceImmediateUplink = true;

  // Move state machine to SEND to transmit immediately.
  if (deviceState == DEVICE_STATE_SLEEP || deviceState == DEVICE_STATE_CYCLE) {
    deviceState = DEVICE_STATE_SEND;
  }

  Serial.printf("Door changed -> %s (immediate uplink)\n", stableDoorOpen ? "OPEN" : "CLOSED");
}

bool buildPayload() {
  unsigned long now = millis();
  const bool periodicDue = (now - lastMeasure >= measurementInterval);
  if (!forceImmediateUplink && !periodicDue) {
    return false;
  }

  const bool sendDueToDoorChange = forceImmediateUplink;
  forceImmediateUplink = false;

  bool doorOpen = stableDoorOpen;

  // Measure temperature on every transmission.
  measureTemperature();

  uint8_t doorByte = doorOpen ? 1 : 0;

  // Payload: temp int16 (x100) + door state byte
  appDataSize = 3;
  appData[0] = (lastTempRaw >> 8) & 0xFF;
  appData[1] = lastTempRaw & 0xFF;
  appData[2] = doorByte;

  if (sendDueToDoorChange && !periodicDue) {
    Serial.printf("Door event uplink -> tempRaw=%d, door=%s, payload=%02X %02X %02X\n",
                  (int)lastTempRaw,
                  doorOpen ? "OPEN" : "CLOSED",
                  appData[0], appData[1], appData[2]);
  } else {
    Serial.printf("Periodic uplink -> tempRaw=%d, door=%s, payload=%02X %02X %02X\n",
                  (int)lastTempRaw,
                  doorOpen ? "OPEN" : "CLOSED",
                  appData[0], appData[1], appData[2]);
  }

  lastMeasure = now;
  oledDraw(lastTempC, doorOpen);
  return true;
}

void setup() {
  Serial.begin(115200);
  delay(100);

  keepOLEDOn();

  Mcu.begin(HELTEC_BOARD, SLOW_CLK_TPYE);

  ds18b20.begin();
  ds18b20.setResolution(12);

  pinMode(DOOR_PIN, INPUT_PULLUP);
  stableDoorOpen = readDoorOpen();
  rawDoorSample = stableDoorOpen;
  doorSampleChangedAtMs = millis();
  lastDoorPollAtMs = 0;

  factory_display.init();
  factory_display.flipScreenVertically();
  oledDraw(NAN, stableDoorOpen);

  // Start periodic timer and force first send immediately.
  lastMeasure = millis() - measurementInterval;

  Serial.println("=== LoRa node: periodic 5min + immediate door events ===");
}

void loop() {
  keepOLEDOn();
  handleDoorChangeEvent();

  switch (deviceState) {
    case DEVICE_STATE_INIT:
      LoRaWAN.init(loraWanClass, loraWanRegion);
      LoRaWAN.setDefaultDR(5);
      deviceState = DEVICE_STATE_JOIN;
      break;

    case DEVICE_STATE_JOIN:
      LoRaWAN.join();
      break;

    case DEVICE_STATE_SEND:
      if (buildPayload()) {
        LoRaWAN.send();
      }
      deviceState = DEVICE_STATE_CYCLE;
      break;

    case DEVICE_STATE_CYCLE:
      txDutyCycleTime = appTxDutyCycle + randr(-APP_TX_DUTYCYCLE_RND, APP_TX_DUTYCYCLE_RND);
      LoRaWAN.cycle(txDutyCycleTime);
      deviceState = DEVICE_STATE_SLEEP;
      break;

    case DEVICE_STATE_SLEEP:
      if (forceImmediateUplink || (millis() - lastMeasure >= measurementInterval)) {
        deviceState = DEVICE_STATE_SEND;
      } else {
        keepOLEDOn();
        LoRaWAN.sleep(loraWanClass);
      }
      break;

    default:
      deviceState = DEVICE_STATE_INIT;
      break;
  }
}
