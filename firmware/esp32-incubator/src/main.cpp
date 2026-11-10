#include "config.h"
#include <string.h>
#include <stdio.h>
#include "pid_controller.h"
#include "sensor_manager.h"
#include "actuator_manager.h"
#include "lcd_manager.h"
#include "telemetry_client.h"

/**
 * =============================================================================
 * Smart Infant Incubator - Production Firmware
 * 
 * FreeRTOS Dual-Core Architecture:
 *   - Core 1 (Pro-CPU): Real-Time PID Temperature Regulation, Sensor Acquisition,
 *                       MOSFET/Fan PWM, LCD Formatting, and Hardware Fail-Safe Guard.
 *   - Core 0 (App-CPU): Wi-Fi Connection Manager, Telemetry Queue Consumer,
 *                       HTTP/HTTPS POST Client, Piggybacked Config & Baby Sync.
 * =============================================================================
 */

// Shared Runtime Configuration
struct DeviceRuntimeConfig {
    uint32_t config_version;
    float temperature_setpoint;
    float pid_kp;
    float pid_ki;
    float pid_kd;
    char fan_mode[16];
    uint8_t fan_pwm_manual;
    bool blue_led_enabled;
    uint32_t telemetry_interval_sec;
};

// Shared Pairing Information
struct DevicePairingState {
    bool is_paired;
    char baby_code[32];
    char baby_name[64];
};

// FreeRTOS Synchronization Handles
static QueueHandle_t telemetryQueue = nullptr;
static SemaphoreHandle_t configMutex = nullptr;

static TaskHandle_t controlTaskHandle = nullptr;
static TaskHandle_t networkTaskHandle = nullptr;

// Global Shared State (Protected by configMutex)
static DeviceRuntimeConfig runtimeConfig;
static DevicePairingState pairingState;
static volatile uint32_t appliedConfigVersion = 1;

// Hardware Component Instances
static SensorManager sensorManager;
static ActuatorManager actuatorManager;
static LCDManager lcdManager;
static PIDController pidController(DEFAULT_PID_KP, DEFAULT_PID_KI, DEFAULT_PID_KD, PID_OUT_MIN, PID_OUT_MAX);
static TelemetryClient telemetryClient;

// =============================================================================
// CORE 1: REAL-TIME CONTROL TASK (High Priority)
// =============================================================================
void realtimeControlTask(void* pvParameters) {
    Serial.println("[CORE 1] Starting Real-Time Control Engine...");

    // Initialize hardware peripherals on Core 1
    sensorManager.begin();
    actuatorManager.begin();
    lcdManager.begin();
    pidController.setOutputLimits(PID_OUT_MIN, PID_OUT_MAX);

    TickType_t xLastWakeTime = xTaskGetTickCount();
    unsigned long lastPidComputeTime = millis();
    unsigned long lastLcdUpdateTime = 0;
    unsigned long lastTelemetryQueueTime = 0;

    for (;;) {
        unsigned long now = millis();

        // 1. Fetch current runtime configuration safely under mutex
        DeviceRuntimeConfig activeConfig;
        uint32_t activeConfigVersion = 1;
        if (xSemaphoreTake(configMutex, pdMS_TO_TICKS(5)) == pdTRUE) {
            activeConfig = runtimeConfig;
            activeConfigVersion = appliedConfigVersion;
            xSemaphoreGive(configMutex);
        } else {
            // If mutex briefly busy, continue with last known safe tunings
            activeConfig.temperature_setpoint = TEMP_SETPOINT_DEFAULT;
            activeConfig.pid_kp = DEFAULT_PID_KP;
            activeConfig.pid_ki = DEFAULT_PID_KI;
            activeConfig.pid_kd = DEFAULT_PID_KD;
            strncpy(activeConfig.fan_mode, "auto", sizeof(activeConfig.fan_mode) - 1);
            activeConfig.fan_pwm_manual = 0;
            activeConfig.blue_led_enabled = false;
            activeConfig.telemetry_interval_sec = DEFAULT_TELEMETRY_SEC;
            activeConfigVersion = 1;
        }

        // Apply dynamic tuning parameters to PID
        pidController.setTunings(activeConfig.pid_kp, activeConfig.pid_ki, activeConfig.pid_kd);

        // 2. Non-blocking sensor update & Fail-Safe Evaluation
        bool failSafeActive = sensorManager.update(now);
        float currentTempObj = sensorManager.getTemperatureObject();

        // 3. Actuator & PID Control Logic
        if (failSafeActive) {
            // HARDWARE FAIL-SAFE TRIP:
            // Immediately disengage heater PWM to 0% upon sensor read failure,
            // disconnection (-127°C / 85°C), or thermal safety boundary breach.
            actuatorManager.setHeaterPWM(0, true);
            pidController.reset(); // Zero integral accumulation to prevent windup
        } else {
            // Calculate elapsed delta time in seconds
            float dt = (now - lastPidComputeTime) / 1000.0f;
            if (dt <= 0.001f || dt > 1.0f) dt = 0.1f;
            lastPidComputeTime = now;

            // Execute local PID calculation with anti-windup clamping
            float heaterPwmOutput = pidController.compute(activeConfig.temperature_setpoint, currentTempObj, dt);
            actuatorManager.setHeaterPWM((uint8_t)heaterPwmOutput, false);
        }

        // 4. Fan Speed Regulation
        if (strcmp(activeConfig.fan_mode, "auto") == 0) {
            if (failSafeActive && strcmp(sensorManager.getFailSafeReason(), "OVERHEAT_EMERGENCY") == 0) {
                // Emergency cooling: maximum 100% fan duty cycle
                actuatorManager.setFanPWM(255);
            } else if (currentTempObj > activeConfig.temperature_setpoint + 0.3f) {
                // Progressive thermal dissipation above target setpoint
                float excess = currentTempObj - activeConfig.temperature_setpoint;
                float fanDuty = 80.0f + (excess / 1.5f) * 175.0f;
                actuatorManager.setFanPWM((uint8_t)constrain(fanDuty, 80.0f, 255.0f));
            } else {
                // Baseline chamber air circulation (25% duty cycle)
                actuatorManager.setFanPWM(64);
            }
        } else {
            // Manual PWM mode configured from dashboard
            actuatorManager.setFanPWM(activeConfig.fan_pwm_manual);
        }

        // 5. Blue LED Indicator (Phototherapy simulation)
        actuatorManager.setBlueLED(activeConfig.blue_led_enabled);

        // 6. LCD Display Refresh (Rate: 2 Hz / 500ms)
        if (now - lastLcdUpdateTime >= LCD_REFRESH_INTERVAL_MS) {
            bool paired = false;
            char babyCode[32] = {0};

            if (xSemaphoreTake(configMutex, pdMS_TO_TICKS(5)) == pdTRUE) {
                paired = pairingState.is_paired;
                strncpy(babyCode, pairingState.baby_code, sizeof(babyCode) - 1);
                xSemaphoreGive(configMutex);
            }

            lcdManager.update(currentTempObj,
                              sensorManager.getHumidity(),
                              actuatorManager.getHeaterPWM(),
                              failSafeActive,
                              sensorManager.getFailSafeReason(),
                              paired,
                              babyCode,
                              DEFAULT_DEVICE_CODE);

            lastLcdUpdateTime = now;
        }

        // 7. Push Telemetry Snapshot to Core 0 FreeRTOS Queue
        unsigned long telemetryIntervalMs = activeConfig.telemetry_interval_sec * 1000UL;
        if (telemetryIntervalMs < 1000UL) telemetryIntervalMs = 3000UL;

        if (now - lastTelemetryQueueTime >= telemetryIntervalMs) {
            TelemetrySnapshot snapshot;
            strncpy(snapshot.device_code, DEFAULT_DEVICE_CODE, sizeof(snapshot.device_code) - 1);
            snapshot.applied_config_version = activeConfigVersion;
            snapshot.temperature_object = currentTempObj;
            snapshot.temperature_room = sensorManager.getTemperatureRoom();
            snapshot.humidity = sensorManager.getHumidity();
            snapshot.heater_pwm = actuatorManager.getHeaterPWM();
            snapshot.fan_pwm = actuatorManager.getFanPWM();
            snapshot.blue_led_status = actuatorManager.getBlueLEDStatus();
            snapshot.ds18b20_ok = sensorManager.isDS18B20Ok();
            snapshot.dht22_ok = sensorManager.isDHT22Ok();
            strncpy(snapshot.ds18b20_status, sensorManager.getDS18B20StatusString(), sizeof(snapshot.ds18b20_status) - 1);
            strncpy(snapshot.dht22_status, sensorManager.getDHT22StatusString(), sizeof(snapshot.dht22_status) - 1);
            snapshot.uptime_ms = now;

            // Non-blocking queue send (timeout = 0): ensures Core 1 NEVER blocks even if queue is full
            if (xQueueSend(telemetryQueue, &snapshot, (TickType_t)0) != pdPASS) {
                Serial.println("[CORE 1] Telemetry queue full; dropping frame to prioritize control.");
            }

            lastTelemetryQueueTime = now;
        }

        // Maintain steady 100ms cycle period (10 Hz)
        vTaskDelayUntil(&xLastWakeTime, pdMS_TO_TICKS(CONTROL_LOOP_PERIOD_MS));
    }
}

// =============================================================================
// CORE 0: NETWORK & TELEMETRY TASK (Medium Priority)
// =============================================================================
void telemetryNetworkTask(void* pvParameters) {
    Serial.println("[CORE 0] Starting Network & Telemetry Dispatcher...");

    telemetryClient.begin(DEFAULT_WIFI_SSID, DEFAULT_WIFI_PASS, DEFAULT_SERVER_URL, DEFAULT_DEVICE_TOKEN);

    TelemetrySnapshot incomingSnapshot;

    for (;;) {
        unsigned long now = millis();

        // 1. Maintain Wi-Fi state non-blockingly
        telemetryClient.maintainWiFi(now);

        // 2. Dequeue telemetry item with 500ms block timeout
        if (xQueueReceive(telemetryQueue, &incomingSnapshot, pdMS_TO_TICKS(500)) == pdPASS) {
            if (telemetryClient.isWiFiConnected()) {
                Serial.printf("[CORE 0] Dispatching telemetry (Obj: %.2f°C, Htr: %d%%)...\n",
                              incomingSnapshot.temperature_object,
                              (incomingSnapshot.heater_pwm * 100) / 255);

                TelemetryResponse response = telemetryClient.sendTelemetry(incomingSnapshot);

                if (response.success) {
                    Serial.printf("[CORE 0] Telemetry ingested successfully (HTTP %d). Paired: %s\n",
                                  response.httpCode, response.is_paired ? "YES" : "NO");

                    // Thread-safe update of pairing and piggybacked configuration
                    if (xSemaphoreTake(configMutex, pdMS_TO_TICKS(100)) == pdTRUE) {
                        pairingState.is_paired = response.is_paired;
                        if (response.is_paired) {
                            strncpy(pairingState.baby_code, response.baby_code, sizeof(pairingState.baby_code) - 1);
                            strncpy(pairingState.baby_name, response.baby_name, sizeof(pairingState.baby_name) - 1);
                        } else {
                            pairingState.baby_code[0] = '\0';
                            pairingState.baby_name[0] = '\0';
                        }

                        // Inspect piggybacked configuration payload
                        if (response.config_update.has_update) {
                            Serial.printf("[CORE 0] Received Piggybacked Config v%u (Setpoint: %.1f°C, Kp: %.2f)\n",
                                          response.config_update.server_config_version,
                                          response.config_update.temperature_setpoint,
                                          response.config_update.pid_kp);

                            runtimeConfig.config_version = response.config_update.server_config_version;
                            runtimeConfig.temperature_setpoint = response.config_update.temperature_setpoint;
                            runtimeConfig.pid_kp = response.config_update.pid_kp;
                            runtimeConfig.pid_ki = response.config_update.pid_ki;
                            runtimeConfig.pid_kd = response.config_update.pid_kd;
                            strncpy(runtimeConfig.fan_mode, response.config_update.fan_mode, sizeof(runtimeConfig.fan_mode) - 1);
                            runtimeConfig.fan_pwm_manual = response.config_update.fan_pwm_manual;
                            runtimeConfig.blue_led_enabled = response.config_update.blue_led_enabled;
                            runtimeConfig.telemetry_interval_sec = response.config_update.telemetry_interval_sec;

                            // Update applied version to acknowledge in next telemetry payload
                            appliedConfigVersion = response.config_update.server_config_version;
                        }

                        xSemaphoreGive(configMutex);
                    }
                } else {
                    Serial.printf("[CORE 0] Telemetry upload failed (%s, HTTP %d)\n",
                                  response.errorMessage, response.httpCode);
                }
            } else {
                Serial.println("[CORE 0] Wi-Fi offline; skipping telemetry dispatch. Core 1 continues local PID control.");
            }
        }

        vTaskDelay(pdMS_TO_TICKS(50));
    }
}

// =============================================================================
// ARDUINO SETUP & BACKGROUND LOOP
// =============================================================================
void setup() {
    Serial.begin(115200);
    delay(500);
    Serial.println("\n=============================================");
    Serial.println("  Smart Infant Incubator - ESP32 Firmware    ");
    Serial.printf ("  Version: %s\n", FIRMWARE_VERSION);
    Serial.println("=============================================");

    // 1. Initialize Default Runtime Configuration
    runtimeConfig.config_version = 1;
    runtimeConfig.temperature_setpoint = TEMP_SETPOINT_DEFAULT;
    runtimeConfig.pid_kp = DEFAULT_PID_KP;
    runtimeConfig.pid_ki = DEFAULT_PID_KI;
    runtimeConfig.pid_kd = DEFAULT_PID_KD;
    strncpy(runtimeConfig.fan_mode, "auto", sizeof(runtimeConfig.fan_mode) - 1);
    runtimeConfig.fan_pwm_manual = 0;
    runtimeConfig.blue_led_enabled = false;
    runtimeConfig.telemetry_interval_sec = DEFAULT_TELEMETRY_SEC;

    pairingState.is_paired = false;
    pairingState.baby_code[0] = '\0';
    pairingState.baby_name[0] = '\0';

    // 2. Create FreeRTOS Synchronization Primitives
    telemetryQueue = xQueueCreate(TELEMETRY_QUEUE_LENGTH, sizeof(TelemetrySnapshot));
    configMutex = xSemaphoreCreateMutex();

    if (telemetryQueue == nullptr || configMutex == nullptr) {
        Serial.println("[FATAL] Failed to create FreeRTOS primitives! Halting.");
        while (1) delay(1000);
    }

    // 3. Launch Core 1 Real-Time Engine Task (High Priority)
    xTaskCreatePinnedToCore(
        realtimeControlTask,
        "ControlCore1",
        TASK_CONTROL_STACK_SIZE,
        NULL,
        TASK_CONTROL_PRIORITY,
        &controlTaskHandle,
        TASK_CONTROL_CORE
    );

    // 4. Launch Core 0 Network & Telemetry Task (Medium Priority)
    xTaskCreatePinnedToCore(
        telemetryNetworkTask,
        "NetworkCore0",
        TASK_NETWORK_STACK_SIZE,
        NULL,
        TASK_NETWORK_PRIORITY,
        &networkTaskHandle,
        TASK_NETWORK_CORE
    );

    Serial.println("[SYSTEM] Dual-core tasks pinned and running.");
}

void loop() {
    // Arduino loop task is unneeded as tasks run independently on Core 0 and Core 1.
    // Sleep peacefully to yield CPU.
    vTaskDelay(pdMS_TO_TICKS(1000));
}
