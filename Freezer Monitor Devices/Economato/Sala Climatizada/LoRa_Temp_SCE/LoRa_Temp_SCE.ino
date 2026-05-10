#include "LoRaWan_APP.h"
#include <OneWire.h>
#include <DallasTemperature.h>
#include "HT_SSD1306Wire.h"
#include <math.h>

/* -------------------- CONFIGURAÇÕES -------------------- */
const char* DEVICE_NAME = "sala climatizada economato";
const int ONE_WIRE_PIN = 1;
const int DOOR_PIN = 7;
const uint8_t DOOR_OPEN_VALUE = HIGH;
static float lastSentTemp = -127.0;

/* -------------------- CHAVES LORAWAN (OTAA) -------------------- */
uint8_t devEui[] = { 0x78, 0x34, 0x61, 0x85, 0x36, 0x30, 0xfe, 0x0c };/*783461853630fe0c*/
uint8_t appEui[] = { 0x64, 0xfe, 0xe4, 0xa0, 0x59, 0xed, 0x44, 0x24 };/*64fee4a059ed4424*/
uint8_t appKey[] = { 0x79, 0xe0, 0xac, 0x4b, 0x40, 0x6f, 0xf2, 0x37, 0xa2, 0x37, 0x5c, 0x76, 0x3a, 0x6f, 0x3c, 0x47 };/*79e0ac4b406ff237a2375c763a6f3c47*/

/* VARIÁVEIS OBRIGATÓRIAS PARA COMPILAÇÃO HELTEC (Mesmo em OTAA) */
uint8_t nwkSKey[] = {0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00};
uint8_t appSKey[] = {0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00};
uint32_t devAddr = 0x00000000;

/* Parâmetros LoRaWAN */
uint16_t userChannelsMask[6] = { 0x00FF, 0x0000, 0x0000, 0x0000, 0x0000, 0x0000 };
LoRaMacRegion_t loraWanRegion = LORAMAC_REGION_EU868;
DeviceClass_t loraWanClass = CLASS_A;
uint32_t appTxDutyCycle = 300000UL; // 5 minutos
bool overTheAirActivation = true;
bool loraWanAdr = true;
bool isTxConfirmed = false;
uint8_t appPort = 2;
uint8_t confirmedNbTrials = 3;

/* -------------------- PERIFÉRICOS E ESTADOS -------------------- */
OneWire oneWire(ONE_WIRE_PIN);
DallasTemperature ds18b20(&oneWire);
SSD1306Wire factory_display(0x3c, 500000, SDA_OLED, SCL_OLED, GEOMETRY_128_64, RST_OLED);

static float lastTempC = NAN;
static int16_t lastTempRaw = (int16_t)0x8000;
static bool stableDoorOpen = true;
static bool lastSentDoorOpen = true;
volatile bool doorInterruptFired = false;

/* -------------------- FUNÇÕES DE APOIO -------------------- */

void IRAM_ATTR doorISR() {
  doorInterruptFired = true;
}

void keepOLEDOn() {
  pinMode(Vext, OUTPUT);
  digitalWrite(Vext, LOW); 
}

bool readDoorOpen() {
  return digitalRead(DOOR_PIN) == DOOR_OPEN_VALUE;
}

bool readDoorOpenStable() {
  uint8_t openCount = 0;
  for (uint8_t i = 0; i < 7; i++) {
    if (readDoorOpen()) openCount++;
    delay(2);
  }
  return (openCount >= 4);
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
  factory_display.drawString(0, 50, doorOpen ? "Porta: ABERTA" : "Porta: FECHADA");
  factory_display.display();
}

void measureTemperature() {
  ds18b20.requestTemperatures();
  float tempC = ds18b20.getTempCByIndex(0);
  if (tempC <= -127.0f || tempC >= 85.0f) {
    lastTempC = NAN;
    lastTempRaw = (int16_t)0x8000;
  } else {
    lastTempC = tempC;
    lastTempRaw = (int16_t)lroundf(tempC * 100.0f);
  }
}

bool buildPayload() {
  stableDoorOpen = readDoorOpenStable();
  lastSentDoorOpen = stableDoorOpen;
  
  measureTemperature();
  lastSentTemp = lastTempC; // <--- ADICIONA ESTA LINHA AQUI

  appDataSize = 3;
  appData[0] = (lastTempRaw >> 8) & 0xFF;
  appData[1] = lastTempRaw & 0xFF;
  appData[2] = stableDoorOpen ? 1 : 0;

  Serial.printf("\n[TX] Temp: %.2f | Porta: %s\n", lastTempC, stableDoorOpen ? "ABERTA" : "FECHADA");
  oledDraw(lastTempC, stableDoorOpen);
  return true;
}

void esperaInterrompivel(uint32_t ms) {
  uint32_t start = millis();
  
  while ((millis() - start < ms) && !doorInterruptFired) {
    LoRaWAN.sleep(loraWanClass);
    delay(10); 

    static uint32_t ultimaLeituraLocal = 0;
    if (millis() - ultimaLeituraLocal > 10000) {
      measureTemperature();
      stableDoorOpen = readDoorOpenStable();
      oledDraw(lastTempC, stableDoorOpen);
      ultimaLeituraLocal = millis();
      
      // SÓ ENTRA AQUI SE JÁ TIVERMOS UMA LEITURA VÁLIDA ENVIADA ANTES
      if (!isnan(lastTempC) && lastSentTemp > -50.0) { 
        if (abs(lastTempC - lastSentTemp) > 1.0) {
           Serial.println("Variação térmica detectada! Antecipando envio...");
           break; 
        }
      }
    }
  }
}

/* -------------------- ARDUINO SETUP -------------------- */
void setup() {
  Serial.begin(115200);
  Mcu.begin(HELTEC_BOARD, SLOW_CLK_TPYE);
  
  ds18b20.begin();
  ds18b20.setResolution(12);

  pinMode(DOOR_PIN, INPUT_PULLUP);
  stableDoorOpen = readDoorOpenStable();
  lastSentDoorOpen = stableDoorOpen;
  
  attachInterrupt(digitalPinToInterrupt(DOOR_PIN), doorISR, CHANGE);

  factory_display.init();
  factory_display.flipScreenVertically();
  oledDraw(NAN, stableDoorOpen);
  
  Serial.println("Node Heltec V3 pronto e aguardando...");
}

/* -------------------- ARDUINO LOOP -------------------- */
void loop() {
  keepOLEDOn();

  if (doorInterruptFired) {
    doorInterruptFired = false;
    bool currentStatus = readDoorOpenStable();
    if (currentStatus != lastSentDoorOpen) {
      Serial.println("Porta mexeu! Acordando...");
      deviceState = DEVICE_STATE_SEND;
    }
  }

  switch (deviceState) {
    case DEVICE_STATE_INIT:
      LoRaWAN.init(loraWanClass, loraWanRegion);
      deviceState = DEVICE_STATE_JOIN;
      break;

    case DEVICE_STATE_JOIN:
      LoRaWAN.join(); // Ele fica aqui até conseguir o Join
      break;

    case DEVICE_STATE_SEND:
      if (buildPayload()) {
        LoRaWAN.send();
      }
      deviceState = DEVICE_STATE_CYCLE;
      break;

    case DEVICE_STATE_CYCLE:
      txDutyCycleTime = appTxDutyCycle + randr(-APP_TX_DUTYCYCLE_RND, APP_TX_DUTYCYCLE_RND);
      deviceState = DEVICE_STATE_SLEEP;
      break;

    case DEVICE_STATE_SLEEP:
      esperaInterrompivel(txDutyCycleTime);
      deviceState = DEVICE_STATE_SEND;
      break;

    default:
      // Nunca voltar para INIT a menos que seja estritamente necessário
      deviceState = DEVICE_STATE_SLEEP; 
      break;
  }
}
