#ifndef PLC_SHARED_H
#define PLC_SHARED_H
#include <stdint.h>
#ifdef __cplusplus
extern "C"
{
#endif

    void plc_reset_state(void);
    int plc_compromised(void);
    void process_production_order(const char *order);

    uint8_t get_conveyor_run(void);
    uint8_t get_emergency_ok(void);
    uint32_t get_quality_score(void);

#ifdef __cplusplus
}
#endif
#endif
